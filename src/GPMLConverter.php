<?php
namespace WikiPathways;

function write_to_stream($pipes, $proc) {
	wfDebug("write_to_stream(" . json_encode($pipes) . ", " . json_encode($proc) . ") called\n");
	return function($data, $end) use($pipes, $proc) {
		try{
			$stdin = $pipes[0];
			$stdout = $pipes[1];
			$stderr = $pipes[2];

			fwrite($stdin, $data);

			if (!isset($end) || $end != true) {
				wfDebug("Ending stream\n");
				return write_to_stream($pipes, $proc);
			}

			fclose($stdin);

			$result = stream_get_contents($stdout);
			$info = stream_get_meta_data($stdout);
			$err = stream_get_contents($stderr);

			fclose($stderr);

			if ($info['timed_out']) {
				wfDebug("Error: pipe timed out\n");
				trigger_error('pipe timed out', E_USER_NOTICE);
			}


			proc_close($proc);

			if ($err) {
				wfDebug("Error in write_to_stream():\n");
				wfDebug($err);
				wfDebug("\n");
				trigger_error($err, E_USER_NOTICE);
			}

			return $result;
		} catch(Exception $e) {
			proc_close($proc);
			wfDebug("Error in write_to_stream():\n");
			wfDebug($e);
			wfDebug("\n");
			trigger_error($e, E_USER_NOTICE);
		}
	};
};

function create_stream($cmd, $opts = array()) {
	wfDebug("create_stream($cmd, " . json_encode($opts) . ") called\n");
	$timeout = $opts["timeout"];

	$proc = proc_open("cat - | $cmd",
		array(
			array("pipe","r"),
			array("pipe","w"),
			array("pipe","w")
		),
		$pipes);

	if (is_resource($proc)) {
		// $pipes now looks like this:
		// 0 => writeable handle connected to child stdin
		// 1 => readable handle connected to child stdout
		// Any error output will be appended to /tmp/error-output.txt

		if (isset($timeout)) {
			stream_set_timeout($pipes[0], $timeout);
		}

		return write_to_stream($pipes, $proc);
	} else {
		wfDebug("Error: $proc for $cmd must be a resource.");
		return false;
	}
}

# TODO do we want to use trigger_error and try/catch/finally, or is it enough to just return false?
class GPMLConverter{
	// TODO is there a better way to define these?
	public static $gpml2pvjson_path="/nix/var/nix/profiles/default/bin/gpml2pvjson";
	public static $bridgedb_path="/nix/var/nix/profiles/default/bin/bridgedb";
	public static $jq_path="/nix/var/nix/profiles/default/bin/jq";
	public static $pvjs_path="/nix/var/nix/profiles/default/bin/pvjs";

	function __construct() {
		// do something
	}

	public static function gpml2pvjson($gpml, $opts) {
		$gpml2pvjson_path = self::$gpml2pvjson_path;
		$bridgedb_path = self::$bridgedb_path;
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		if (empty($gpml)) {
			wfDebug("Error: invalid gpml provided");
			return;
		}

		$identifier = escapeshellarg($opts["identifier"]);
		$version = escapeshellarg($opts["version"]);
		$organism = escapeshellarg($opts["organism"]);

		$toPvjsonCmd = <<<TEXT
$gpml2pvjson_path --id $identifier --pathway-version $version | \
$jq_path -rc '. as {\$pathway} | (.entityMap | .[] |= (.type += if .dbId then [.dbConventionalName + ":" + .dbId] else [] end )) as \$entityMap | {\$pathway, \$entityMap}'
TEXT;
		$rawPvjsonString = '';
		try{
			//TODO: this timeout should be updated or removed when we get async caching working
			$streamGpml2Pvjson = create_stream("$toPvjsonCmd", array("timeout" => 10));
			$rawPvjsonString = $streamGpml2Pvjson($gpml, true);
		} catch(Exception $e) {
			wfDebug("Error converting GPML to PVJSON:");
			wfDebug($e);
			wfDebug("\n");
			return $rawPvjsonString;
		}

		$xrefsBatchCmd = <<<TEXT
$jq_path -rc '.entityMap[] | select(has("dbId") and has("dbConventionalName") and .gpmlElementName == "DataNode" and (.wpType == "GeneProduct" or .wpType == "Protein" or .wpType == "Rna" or .wpType == "Metabolite") and .dbConventionalName != "undefined" and .dbId != "undefined") | .dbConventionalName + "," + .dbId' | \
$bridgedb_path xrefsBatch --organism $organism | \
$jq_path -rc --slurp 'reduce .[] as \$entity ({}; .[\$entity.dbConventionalName + ":" + \$entity.dbId] = \$entity)';
TEXT;
		$bridgedbResultString = '';

		try{
			//TODO: this timeout should be updated or removed when we get async caching working
			$writeToBridgeDbStream = create_stream("$xrefsBatchCmd", array("timeout" => 10));
			$bridgedbResultString = $writeToBridgeDbStream($rawPvjsonString, true);
		} catch(Exception $e) {
			wfDebug("Error using BridgeDb to unify Xrefs:");
			wfDebug($e);
			wfDebug("\n");
			return $rawPvjsonString;
		}


		// TODO Are we actually saving any time by doing this instead of just parsing it as JSON?
		if (!$bridgedbResultString || empty($bridgedbResultString) || $bridgedbResultString == '{}' || $bridgedbResultString == '[]') {
			return $rawPvjsonString;
		}

		// TODO use jq to stream result and only extract what we need
		if (strlen($bridgedbResultString) > 5 * 1000 * 1000) {
			return $rawPvjsonString;
		}

		try{
			$bridgedbResult = json_decode($bridgedbResultString);
			$pvjson = json_decode($rawPvjsonString);
			$pathway = $pvjson->pathway;
			$entityMap = $pvjson->entityMap;
			foreach ($entityMap as $key => $value) {
				if (property_exists($value, 'dbConventionalName') && property_exists($value, 'dbId')) {
					$xrefId = $value->dbConventionalName.":".$value->dbId;
					if (property_exists($bridgedbResult, $xrefId)) {
						$mapper = $bridgedbResult->$xrefId;
						if (property_exists($mapper, 'xrefs')) {
							$xrefs = $mapper->xrefs;
							foreach ($xrefs as $xref) {
								if (property_exists($xref, 'isDataItemIn') && property_exists($xref, 'dbId')) {
									$datasource = $xref->isDataItemIn;
									if (property_exists($datasource, 'preferredPrefix')) {
										array_push($value->type, "$datasource->preferredPrefix:$xref->dbId");
									}
								}
							}
						}
					}
				}
			}

			return json_encode(array("pathway"=>$pathway, "entityMap"=>$entityMap));
		} catch(Exception $e) {
			wfDebug("Error integrating unified xrefs with pvjson:");
			wfDebug($e);
			wfDebug("\n");
			return $rawPvjsonString;
		}
	}

	public static function pvjson2svg($pvjson, $opts) {
		$jq_path = self::$jq_path;
		$pvjs_path = self::$pvjs_path;

		if (empty($pvjson) || trim($pvjson) == '{}') {
			wfDebug("Error: invalid pvjson provided\n");
			wfDebug(json_encode($pvjson));
			wfDebug("\n");
			return;
		}

		$static = isset($opts["static"]) ? $opts["static"] : false;

		try{
			$streamPvjsonToSvg = create_stream("$pvjs_path json2svg -s $static", array("timeout" => 10));
			return $streamPvjsonToSvg($pvjson, true);
		} catch(Exception $e) {
			wfDebug("Error converting PVJSON to SVG:");
			wfDebug($e);
			wfDebug("\n");
			return;
		}

	}

}
