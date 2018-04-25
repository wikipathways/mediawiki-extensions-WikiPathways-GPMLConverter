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

use GlobalVarConfig;
use Exception;

# TODO do we want to use trigger_error and try/catch/finally, or is it enough to just return false?
class Converter {

	private static $svgThemes = [
		"plain" => "plain",
		"dark" => "dark",
		"pretty" => "dark",
	];

	private $pathwayID;
	private $format;

	public function __construct( $pathwayID, $format = "json" ) {
		$this->pathwayID = $pathwayID;
		$this->format = $format;
	}

	public function setOutput( $name ) {
		$this->outfh = fopen( $name );
	}

	public static function writeStream( $pipes, $proc ) {
		return function ( $data, $end ) use( $pipes, $proc ) {
			try{
				$stdin = $pipes[0];
				$stdout = $pipes[1];
				$stderr = $pipes[2];

				fwrite( $stdin, $data );

				if ( !isset( $end ) || $end != true ) {
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
					error_log( $err );
				}

				return $result;
			} catch ( Exception $e ) {
				proc_close( $proc );
				wfDebugLog( 'GPMLConverter', "Error in self::writeStream():\n" );
				wfDebugLog( 'GPMLConverter', $e );
				wfDebugLog( 'GPMLConverter', "\n" );
				trigger_error( $e, E_USER_NOTICE );
			}
		};
	}

	public static function createStream( $cmd, $opts = [] ) {
		$timeout = $opts["timeout"];

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

	private static function getPath( $pathKey ) {
		$conf = new GlobalVarConfig( "wpi" );
		$path = $conf->get( $pathKey );
		if ( !file_exists( $path ) ) {
			$path = __DIR__ . "/../" . $path;
		}
		return $path;
	}

	public static function gpml2pvjson( $gpml, $opts ) {
		$gpml2pvjsonPath = self::getPath( "gpml2pvjsonPath" );
		$bridgedbPath = self::getPath( "bridgedbPath" );
		$jqPath = self::getPath( "jqPath" );

		if ( !$gpml ) {
			throw new \MWException( "Error: invalid gpml provided" );
			return;
		}

		$identifier = escapeshellarg( $opts["identifier"] );
		$version = escapeshellarg( $opts["version"] );
		$organism = escapeshellarg( $opts["organism"] );

		$toPvjsonCmd = <<<TEXT
$gpml2pvjsonPath --id $identifier --pathway-version $version | \
$jqPath -rc '. as {\$pathway} | (.entityMap | .[] |= (.type += if .dbId then [.dbConventionalName + ":" + .dbId] else [] end )) as \$entityMap | {\$pathway, \$entityMap}'
TEXT;
		$rawPvjsonString = '';
		try{
			// TODO: this timeout should be updated or removed when we get async caching working
			$streamGpml2Pvjson = self::createStream( "$toPvjsonCmd", [ "timeout" => 10 ] );
			$rawPvjsonString = $streamGpml2Pvjson( $gpml, true );
		} catch ( Exception $e ) {
			wfDebugLog( 'GPMLConverter', "Error converting GPML to PVJSON:" );
			wfDebugLog( 'GPMLConverter', $e );
			wfDebugLog( 'GPMLConverter', "\n" );
			return $rawPvjsonString;
		}

		$xrefsBatchCmd = <<<TEXT
$jqPath -rc '.entityMap[] | select(has("dbId") and has("dbConventionalName") and .gpmlElementName == "DataNode" and (.wpType == "GeneProduct" or .wpType == "Protein" or .wpType == "Rna" or .wpType == "Metabolite") and .dbConventionalName != "undefined" and .dbId != "undefined") | .dbConventionalName + "," + .dbId' | \
$bridgedbPath xrefsBatch --organism $organism | \
$jqPath -rc --slurp 'reduce .[] as \$entity ({}; .[\$entity.dbConventionalName + ":" + \$entity.dbId] = \$entity)';
TEXT;
		$bridgedbResultString = '';

		try{
			// TODO: this timeout should be updated or removed when we get async caching working
			$writeToBridgeDbStream = self::createStream( "$xrefsBatchCmd", [ "timeout" => 10 ] );
			$bridgedbResultString = $writeToBridgeDbStream( $rawPvjsonString, true );
		} catch ( Exception $e ) {
			wfDebugLog( 'GPMLConverter', "Error using BridgeDb to unify Xrefs:" );
			wfDebugLog( 'GPMLConverter', $e );
			wfDebugLog( 'GPMLConverter', "\n" );
			return $rawPvjsonString;
		}

		// TODO Are we actually saving any time by doing this instead of just parsing it as JSON?
		if ( !$bridgedbResultString
			 || $bridgedbResultString == '{}' || $bridgedbResultString == '[]' ) {
			return $rawPvjsonString;
		}

		// TODO use jq to stream result and only extract what we need
		if ( strlen( $bridgedbResultString ) > 5 * 1000 * 1000 ) {
			return $rawPvjsonString;
		}

		try{
			$bridgedbResult = json_decode( $bridgedbResultString );
			$pvjson = json_decode( $rawPvjsonString );
			$pathway = $pvjson->pathway;
			$entityMap = $pvjson->entityMap;
			foreach ( $entityMap as $value ) {
				if ( property_exists( $value, 'dbConventionalName' ) && property_exists( $value, 'dbId' ) ) {
					$xrefId = $value->dbConventionalName.":".$value->dbId;
					if ( property_exists( $bridgedbResult, $xrefId ) ) {
						$mapper = $bridgedbResult->$xrefId;
						if ( property_exists( $mapper, 'xrefs' ) ) {
							$xrefs = $mapper->xrefs;
							foreach ( $xrefs as $xref ) {
								if ( property_exists( $xref, 'isDataItemIn' ) && property_exists( $xref, 'dbId' ) ) {
									$datasource = $xref->isDataItemIn;
									if ( property_exists( $datasource, 'preferredPrefix' ) ) {
										array_push( $value->type, "$datasource->preferredPrefix:$xref->dbId" );
									}
								}
							}
						}
					}
				}
			}

			return json_encode( [ "pathway" => $pathway, "entityMap" => $entityMap ] );
		} catch ( Exception $e ) {
			wfDebugLog( 'GPMLConverter', "Error integrating unified xrefs with pvjson:" );
			wfDebugLog( 'GPMLConverter', $e );
			wfDebugLog( 'GPMLConverter', "\n" );
			return $rawPvjsonString;
		}
	}

	public static function pvjson2svg( $pvjson, $opts ) {
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
			$streamPvjsonToSvg = self::createStream(
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
