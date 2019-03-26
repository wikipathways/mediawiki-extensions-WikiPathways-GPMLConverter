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
		# TODO: are we using pathwayID and/or format with the constructor anywhere?
		$this->pathwayID = $pathwayID;
		$this->format = $format;
	}

	/**
	 * @param string $name where to write
	 */
	# TODO: are we using this anywhere?
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

	/**
	 * Convert the given GPML file to another file format.
	 * The file format will be determined by the
	 * output file extension.
	 *
	 * @param string $gpmlFile path to source
	 * @param string $outFile path to destination
	 * @param array $opts=[] options
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

		self::setup( $opts );

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

		$cmd = sprintf(
			'gpml2 --id %s --pathway-version %s %s %s',
			self::$identifier, self::$version, $gpmlFile, $outFile);

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
}
