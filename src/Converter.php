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
use GlobalVarConfig;
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

class Converter {

	private static $svgThemes = [
		"plain" => "plain",
		"dark" => "dark",
		"pretty" => "dark",
	];

	private $pathwayID;
	private $format;
	private $bridgedbResult;

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

	private static $gpml2pvjsonPath;
	private static $bridgedbPath;
	private static $jqPath;
	private static $organism;
	private static $identifier;
	private static $version;
	private static function getPath( $pathKey ) {
		$conf = new GlobalVarConfig( "wpi" );
		$path = $conf->get( $pathKey );
		if ( !file_exists( $path ) ) {
			$path = __DIR__ . "/../" . $path;
		}
		return $path;
	}

	private static function setup( $opts ) {
		self::$gpml2pvjsonPath = self::getPath( "gpml2pvjsonPath" );
		self::$bridgedbPath = self::getPath( "bridgedbPath" );
		self::$jqPath = self::getPath( "jqPath" );
		self::$organism = escapeshellarg( $opts["organism"] );
		self::$identifier = escapeshellarg( $opts["identifier"] );
		self::$version = escapeshellarg( $opts["version"] );
	}

	private static function getToPvjsonCmd( array $opts ) {
		self::setup( $opts );

		return sprintf(
			'%s --id %s --pathway-version %s',
			self::$gpml2pvjsonPath, self::$identifier, self::$version
		) . '|' . sprintf(
			'%s -rc \'. as {$pathway} | (.entityMap | .[] |= (.type += if .dbId then '
			. '[.dbConventionalName + ":" + .dbId] else [] end )) '
			. 'as $entityMap | {$pathway, $entityMap}\'', self::$jqPath
		);
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

	private static function getXrefsBatchCmd( array $opts ) {
		self::setup( $opts );

		return sprintf(
			'%s -rc \'.entityMap[] | select(has("dbId") and has("dbConventionalName") '
			. 'and .gpmlElementName == "DataNode" and '
			. '(.wpType == "GeneProduct" or .wpType == "Protein" or .wpType == "Rna" '
			. 'or .wpType == "Metabolite") and .dbConventionalName != "undefined" and '
			. '.dbId != "undefined") | .dbConventionalName + "," + .dbId\'', self::$jqPath
		) . ' | ' . sprintf(
			'%s xrefsBatch --organism %s', self::$bridgedbPath, self::$organism
		) . ' | ' . sprintf(
			'%s -rc --slurp \'reduce .[] as $entity ({}; .[$entity.dbConventionalName + '
			. '":" + $entity.dbId] = $entity)\'', self::$jqPath
		);
	}

	private static function getBridgeDBOutput( $opts, $rawPvjsonString ) {
		// TODO: this timeout should be updated or removed when we get async caching working
		$xrefsBatchCmd = self::getXrefsBatchCmd( $opts );
		$writeToBridgeDbStream = ConvertStream::createStream( $xrefsBatchCmd, [ "timeout" => 10 ] );
		if ( !$writeToBridgeDbStream ) {
			wfDebugLog( 'GPMLConverter', "Error using BridgeDb to unify Xrefs:" );
			return $rawPvjsonString;
		}
		return $writeToBridgeDbStream( $rawPvjsonString, true );
	}

	private function extractPathwayAndEntityMap( $pvjson ) {
		$pathway = $pvjson->pathway;
		$entityMap = $pvjson->entityMap;
		foreach ( $entityMap as $value ) {
			if (
				property_exists( $value, 'dbConventionalName' )
				&& property_exists( $value, 'dbId' )
			) {
				$xrefId = $value->dbConventionalName.":".$value->dbId;
				if ( property_exists( $this->bridgedbResult, $xrefId ) ) {
					$mapper = $this->bridgedbResult->$xrefId;
					if ( property_exists( $mapper, 'xrefs' ) ) {
						$xrefs = $mapper->xrefs;
						foreach ( $xrefs as $xref ) {
							if (
								property_exists( $xref, 'isDataItemIn' )
								&& property_exists( $xref, 'dbId' )
							) {
								$datasource = $xref->isDataItemIn;
								if ( property_exists( $datasource, 'preferredPrefix' ) ) {
									array_push(
										$value->type, "$datasource->preferredPrefix:$xref->dbId"
									);
								}
							}
						}
					}
				}
			}
		}

		return json_encode( [ "pathway" => $pathway, "entityMap" => $entityMap ] );
	}

	/**
	 * @param string $gpml XML of the gpml
	 * @param array $opts options
	 * @return string|bool false if an error
	 */
	public function gpml2pvjson( $gpml, $opts ) {
		if ( !$gpml ) {
			error_log( "Invalid GPML provided" );
			return false;
		}
		try {
			$rawPvjsonString = self::getPvjsonOutput( $gpml, $opts );
		} catch ( Exception $e ) {
			error_log( "Error from getPvjsonOutput: " . $e->getMessage() );
			return false;
		}

		$bridgedbResultString = self::getBridgeDBoutput( $opts, $rawPvjsonString );

		// TODO Are we actually saving any time by doing this instead of just parsing it as JSON?
		if ( !$bridgedbResultString
			 || $bridgedbResultString == '{}' || $bridgedbResultString == '[]' ) {
			return $rawPvjsonString;
		}

		// TODO use jq to stream result and only extract what we need
		if ( strlen( $bridgedbResultString ) > 5 * 1000 * 1000 ) {
			return $rawPvjsonString;
		}

		$this->bridgedbResult = json_decode( $bridgedbResultString );
		if ( !$this->bridgedbResult ) {
			self::error( "Did not get proper json for bridgeDB" );
			return $rawPvjsonString;
		}

		$pvjson = json_decode( $rawPvjsonString );
		if ( !$pvjson ) {
			self::error( "Did not get proper json for PV" );
			return $rawPvjsonString;
		}

		return $this->extractPathwayAndEntityMap( $pvjson );
	}

	private static function error( $error ) {
		wfDebugLog( 'GPMLConverter', "Error integrating unified xrefs with pvjson:" );
		wfDebugLog( 'GPMLConverter', $error );
		wfDebugLog( 'GPMLConverter', "\n" );
	}

	/**
	 * @param string $pvjson JSON equiv of pv
	 * @param array $opts options
	 * @return string
	 */
	public function getPvjson2svg( $pvjson, $opts ) {
		$pvjsPath = self::getPath( "pvjsPath" );

		if ( empty( $pvjson ) || trim( $pvjson ) == '{}' ) {
			wfDebugLog( 'GPMLConverter', "Error: invalid pvjson provided\n" );
			wfDebugLog( 'GPMLConverter', json_encode( $pvjson ) );
			wfDebugLog( 'GPMLConverter', "\n" );
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
				"$pvjsPath $reactOpt $themeOpt", [ "timeout" => 10 ]
			);
			return $streamPvjsonToSvg( $pvjson, true );
		} catch ( Exception $e ) {
			wfDebugLog( 'GPMLConverter', "Error converting PVJSON to SVG:" );
			wfDebugLog( 'GPMLConverter', $e );
			wfDebugLog( 'GPMLConverter', "\n" );
			return;
		}
	}

}
