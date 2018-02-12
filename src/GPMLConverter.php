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

namespace WikiPathways;

# TODO do we want to use trigger_error and try/catch/finally, or is it enough to just return false?
class GPMLConverter {
	// TODO is there a better way to define these?
	public static $gpml2pvjson_path = "/nix/var/nix/profiles/default/bin/gpml2pvjson";
	public static $bridgedb_path = "/nix/var/nix/profiles/default/bin/bridgedb";
	public static $jq_path = "/nix/var/nix/profiles/default/bin/jq";
	public static $pvjs_path = "/nix/var/nix/profiles/default/bin/pvjs";

	private static $SVG_THEMES = [
		"plain" => "plain",
		"dark" => "dark",
		"pretty" => "dark",
	];

	function __construct() {
		// do something
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
					trigger_error( 'pipe timed out', E_USER_NOTICE );
				}

				proc_close( $proc );

				if ( $err ) {
					trigger_error( $err, E_USER_NOTICE );
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

	public static function gpml2pvjson( $gpml, $opts ) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		if ( empty( $gpml ) ) {
			wfDebugLog( 'GPMLConverter', "Error: invalid gpml provided" );
			return;
		}

		$identifier = escapeshellarg( $opts["identifier"] );
		$version = escapeshellarg( $opts["version"] );
		$organism = escapeshellarg( $opts["organism"] );

		$toPvjsonCmd = <<<TEXT
$gpml2pvjson_path --id $identifier --pathway-version $version | \
$jq_path -rc '. as {\$pathway} | (.entityMap | .[] |= (.type += if .dbId then [.dbConventionalName + ":" + .dbId] else [] end )) as \$entityMap | {\$pathway, \$entityMap}'
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
$jq_path -rc '.entityMap[] | select(has("dbId") and has("dbConventionalName") and .gpmlElementName == "DataNode" and (.wpType == "GeneProduct" or .wpType == "Protein" or .wpType == "Rna" or .wpType == "Metabolite") and .dbConventionalName != "undefined" and .dbId != "undefined") | .dbConventionalName + "," + .dbId' | \
$bridgedb_path xrefsBatch --organism $organism | \
$jq_path -rc --slurp 'reduce .[] as \$entity ({}; .[\$entity.dbConventionalName + ":" + \$entity.dbId] = \$entity)';
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
		if ( !$bridgedbResultString || empty( $bridgedbResultString ) || $bridgedbResultString == '{}' || $bridgedbResultString == '[]' ) {
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
			foreach ( $entityMap as $key => $value ) {
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
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		if ( empty( $pvjson ) || trim( $pvjson ) == '{}' ) {
			wfDebugLog( 'GPMLConverter', "Error: invalid pvjson provided\n" );
			wfDebugLog( 'GPMLConverter', json_encode( $pvjson ) );
			wfDebugLog( 'GPMLConverter', "\n" );
			return;
		}

		$reactOpt = (isset($opts["react"]) && $opts["react"] == true)
				  ? " --react"
				  : "";
		$themeOpt = (isset($opts["theme"]) && self::$SVG_THEMES[$opts["theme"]])
				  ? "--theme " . escapeshellarg(self::$SVG_THEMES[$opts["theme"]])
				  : "";

		try{
			$streamPvjsonToSvg = self::createStream(
				"$pvjs_path $reactOpt $themeOpt", [ "timeout" => 10 ]
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
