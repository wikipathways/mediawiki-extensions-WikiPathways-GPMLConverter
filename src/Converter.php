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

use Exception;


class Converter {

	private static $svgThemes = [
		"plain" => "plain",
		"dark" => "dark",
		"pretty" => "dark",
	];

	private $pathwayID;
	private $format;

	/**
	 * @param string $pathwayID for pathway
	 * @param string $format json or whatever
	 */
	public function __construct( $pathwayID, $format = "json" ) {
		$this->pathwayID = $pathwayID;
		$this->format = $format;
	}

	/**
	 * @param string $name where to write
	 */
	public function setOutput( $name ) {
		$this->outfh = fopen( $name );
	}

	private static $organism;
	private static $identifier;
	private static $version;

	private static function setup( $opts ) {
		self::$organism = escapeshellarg( $opts["organism"] );
		self::$identifier = escapeshellarg( $opts["identifier"] );
		self::$version = escapeshellarg( $opts["version"] );
	}

	private static function getToPvjsonCmd( array $opts ) {
		self::setup( $opts );

		return sprintf(
			'gpml2pvjson --id %s --pathway-version %s',
			self::$identifier, self::$version
		);

/*
		return sprintf(
			'gpml2pvjson --id %s --pathway-version %s',
			self::$identifier, self::$version
		self::$organism = escapeshellarg( $opts["organism"] );
		self::$identifier = escapeshellarg( $opts["identifier"] );
		self::$version = escapeshellarg( $opts["version"] );
*/

	}

	private static function getPvjsonOutput( $gpml, $opts ) {
		// TODO: this timeout should be updated or removed when we get async caching working
		$toPvjsonCmd = self::getToPvjsonCmd( $opts );
		$streamGpml2Pvjson = ConvertStream::createStream( $toPvjsonCmd, [ "timeout" => 10 ] );
		if ( !$streamGpml2Pvjson ) {
			$err = error_get_last();
			throw new \MWException(
				"Error Converting GPML to PVJSON: "
				. $err["message"] . " (" . $err["file"] . ":" . $err["line"] . ")"
			);
			return '';
		}
		return $streamGpml2Pvjson( $gpml, true );
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string|bool false if an error
	 */
	public function gpml2pvjson( $gpml, $opts ) {
		if ( !$gpml ) {
			wfDebugLog( __METHOD__, "Error: invalid gpml provided" );
			return false;
		}
		try {
			$rawPvjsonString = self::getPvjsonOutput( $gpml, $opts );
		} catch ( Exception $e ) {
			wfDebugLog( 'GPMLConverter', "Error converting GPML to PVJSON: " . $e->getMessage() );
			return false;
		}
		return $rawPvjsonString;
	}

	/**
	 * Convert the given GPML file to another file format.
	 * The file format will be determined by the
	 * output file extension.
	 *
	 * @param string $gpmlFile source
	 * @param string $outFile destination
	 * @param array [$opts] options
	 * @return bool
	 */
	public function convert( $gpmlFile, $outFile, $opts = [] ) {
		if ( !$gpmlFile ) {
			wfDebugLog( __METHOD__, "Error: invalid gpml provided" );
			return false;
		}

		if ( file_exists( $outFile ) ) {
			return true;
		}

		/*
		$scaleOpt = isset( $opts["scale"] )
				  ? $opts["scale"]
				  : 100;

		$reactOpt = ( isset( $opts["react"] ) && $opts["react"] == true )
				  ? " --react"
				  : "";
		$themeOpt = ( isset( $opts["theme"] ) && self::$svgThemes[$opts["theme"]] )
				  ? "--theme " . escapeshellarg( self::$svgThemes[$opts["theme"]] )
				  : "";
		*/

		$gpmlFile = realpath( $gpmlFile );

		# TODO: make an alias for the gpmlconverter binary
		$cmd = sprintf(
			'/home/wikipathways/extensions/GPMLConverter/gpmlconverter --id %s --pathway-version %s --organism %s %s %s',
			self::$identifier, self::$version, self::$organism, $gpmlFile, $outFile);
		#$cmd = " '$gpmlFile' '$outFile' 2>&1";

		wfDebugLog( __METHOD__,  "CONVERTER: $cmd\n" );
		$msg = wfShellExec( $cmd, $status, [], [ 'memory' => 0 ] );
		if ( $status != 0 ) {
			throw new MWException(
				"Unable to convert to $outFile:\n\nStatus: $status\n\nMessage: $msg\n\n"
				. "Command: $cmd"
			);
			wfDebugLog( __METHOD__,
				"Unable to convert to $outFile: Status: $status   Message:$msg  "
				. "Command: $cmd"
			);
		} else {
			wfDebugLog( __METHOD__, "Convertible: $cmd" );
		}
		return true;
	}


	/**
	 * @param string $gpml XML of the gpml
	 * @param string $outputFormat file name extension
	 * @param integer|null $scale percent of original. Min 100. Only for GPML to PNG.
	 * @return string
	 */
	public function convertWithPathVisio( $input, $outputFormat, $scale = null ) {

		if ( !$input ) {
			wfDebugLog( __METHOD__, "Error: invalid gpml provided" );
			return false;
		}

		try{
			$tmp_in = tempnam(sys_get_temp_dir(), "pathvisio-");
			$tmp_path_in = $tmp_in . '.gpml';
			rename($tmp_in, $tmp_path_in);
			file_put_contents($tmp_path_in, $input);

			$tmp_path_out = $tmp_path_in . "." . $outputFormat;

			$cmd = sprintf(
				'pathvisio convert %s %s',
				$tmp_path_in, $tmp_path_out
			);
			if (is_int(ctype_digit(strval($scale)))) {
				$cmd .= ' ' . strval($scale);
			}
			$cmd .= ' 2>&1';

			#$msg = exec( $cmd );
			$last_line = exec( $cmd, $msg, $status );

			if ( $status != 0 ) {
				throw new MWException(
					"Unable to convert to $outFormat:\n\nStatus: $status\n\nMessage: $msg\n\n"
					. "Command: $cmd"
				);
				wfDebugLog( __METHOD__,
					"Unable to convert to $outFormat: Status: $status   Message:$msg  "
					. "Command: $cmd"
				);
			} else {
				wfDebugLog( __METHOD__, "Convertible: $cmd" );
			}

			unlink($tmp_path_in);

			$result = '';
			if (file_exists($tmp_path_out)) {
				$result = file_get_contents( $tmp_path_out );
				unlink($tmp_path_out);
			} else {
				wfDebugLog( "The file $tmp_path_out does not exist" );
			}

			return $result;
		} catch ( Exception $e ) {
			wfDebugLog( __METHOD__, "Error converting GPML to $outputFormat:" );
			wfDebugLog( __METHOD__, $e );
			wfDebugLog( __METHOD__, "\n" );
			return;
		}
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string
	 */
	public function getGpml2txt( $input, $opts ) {
		return self::convertWithPathVisio($input, "txt");
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string
	 */
	public function getGpml2png( $input, $opts = [] ) {
		$scaleOpt = isset( $opts["scale"] )
				  ? $opts["scale"]
				  : 100;

		return self::convertWithPathVisio($input, "png", $scaleOpt);
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string
	 */
	public function getGpml2owl( $input, $opts ) {
		return self::convertWithPathVisio($input, "owl");
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string
	 */
	public function getGpml2pdf( $input, $opts ) {
		return self::convertWithPathVisio($input, "pdf");
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string
	 */
	public function getGpml2pwf( $input, $opts ) {
		return self::convertWithPathVisio($input, "pwf");
	}

	/**
	 * @param string $pvjson JSON equiv of pv
	 * @param array $opts options
	 * @return string
	 */
	public function getPvjson2svg( $pvjson, $opts ) {

		if ( empty( $pvjson ) || trim( $pvjson ) == '{}' ) {
			wfDebugLog( __METHOD__, "Error: invalid pvjson provided\n" );
			wfDebugLog( __METHOD__, json_encode( $pvjson ) );
			wfDebugLog( __METHOD__, "\n" );
			return;
		}

		$reactOpt = ( isset( $opts["react"] ) && $opts["react"] == true )
				  ? " --react"
				  : "";
		$themeOpt = ( isset( $opts["theme"] ) && self::$svgThemes[$opts["theme"]] )
				  ? "--theme " . escapeshellarg( self::$svgThemes[$opts["theme"]] )
				  : "";

		try{
			$streamPvjsonToSvg = ConvertStream::createStream(
				"pvjs $reactOpt $themeOpt", [ "timeout" => 10 ]
			);
			return $streamPvjsonToSvg( $pvjson, true );
		} catch ( Exception $e ) {
			wfDebugLog( __METHOD__, "Error converting PVJSON to SVG:" );
			wfDebugLog( __METHOD__, $e );
			wfDebugLog( __METHOD__, "\n" );
			return;
		}
	}

}
