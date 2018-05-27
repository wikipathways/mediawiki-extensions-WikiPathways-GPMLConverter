<?php
/*
 * Copyright (C) 2018  J. David Gladstone Institutes
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author Anders Riutta <git@andersriutta.com>
 * @author Mark A. Hershberger <mah@nichework.com>
 */

namespace WikiPathways\GPML;

use MWException;

class ConvertStream {
	private static $cmd;

	/**
	 * @param array $pipes for input output, etc
	 * @param resource $proc open process
	 * @return bool|function
	 */
	public static function writeStream( $pipes, $proc ) {
		return function ( $data, $end ) use( $pipes, $proc ) {
			$stdin = $pipes[0];
			$stdout = $pipes[1];
			$stderr = $pipes[2];

			$bytes = fwrite( $stdin, $data );

			if ( $bytes === false ) {
				$err = error_get_last();
				if ( $err ) {
					throw new MWException( "Problem writing stream: " . $err['message'] );
				}

				proc_close( $proc );
				return false;
			}

			if ( !isset( $end ) || $end !== true ) {
				wfDebugLog( 'GPMLConverter', "Ending stream\n" );
				return self::writeStream( $pipes, $proc );
			}

			fclose( $stdin );

			$result = stream_get_contents( $stdout );
			$info = stream_get_meta_data( $stdout );
			$err = stream_get_contents( $stderr );

			fclose( $stderr );

			if ( $info['timed_out'] ) {
				wfDebugLog( 'GPMLConverter', "Error: pipe timed out\n" );
				error_log( 'pipe timed out' );
			}

			proc_close( $proc );

			if ( $err ) {
				error_log( "$err for " . self::$cmd );
				throw new MWException( "Error during " . self::$cmd . ": $err" );
			}

			return $result;
		};
	}

	/**
	 * @param string $cmd to run
	 * @param array $opts timeout holder
	 * @return bool|callable
	 */
	public static function createStream( $cmd, $opts = [] ) {
		$timeout = $opts["timeout"];

		self::$cmd = $cmd;
		$proc = proc_open( "cat - | $cmd",
						  [
							  [ "pipe","r" ],
							  [ "pipe","w" ],
							  [ "pipe","w" ]
						  ],
						  $pipes );

		if ( is_resource( $proc ) ) {
			// $pipes now looks like this:
			// 0 => writeable handle connected to child stdin
			// 1 => readable handle connected to child stdout
			// Any error output will be appended to /tmp/error-output.txt

			if ( isset( $timeout ) ) {
				stream_set_timeout( $pipes[0], $timeout );
			}

			return self::writeStream( $pipes, $proc );
		} else {
			return false;
		}
	}
}
