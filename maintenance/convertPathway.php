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
 * @author Mark A. Hershberger
 */
namespace WikiPathways\GPML;

use WikiPathways\Pathway;
use Maintenance;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';
$maintPath = $basePath . '/maintenance/Maintenance.php';
if ( !file_exists( $maintPath ) ) {
	die ( "Please set the environment variable MW_INSTALL_PATH!" );
}
require_once $maintPath;


class ConvertPathway extends Maintenance {
	private $outputFH;

	public function __construct() {
		parent::__construct();

		$this->addDescription( "Convert GPML to another format." );
		$this->addOption( "format", "Format to convert to", true, true, "f" );
		$this->addOption( "stdout", "Print the conversion to stdout", false, false, "s" );
		$this->addOption(
			"revision", "Revision to use.  Current will be used if not given.", true, false, "r"
		);
		$this->addArg( "arg", "Pathway ID (e.g. WP554)" );
	}

	public function execute() {
		$pathwayID = $this->getArg( 0 ); // required, Maintenance.php will die for us
		$format = $this->getOption( "output" ); // required
		$revision = $this->getOption( "revision" ); // null if not provided

		$pathway = new Pathway( $pathwayID );
		if ( $this->hasOption( "revision" ) ) {
			$pathway->setActiveRevision( $this->getOption( "revision" ) );
		}
		$convert = new GPMLConverter( $pathway, $format );
		if ( $this->hasOption( "stdout" ) ) {
			$convert->setOutput( "php://stdout" );
		}
		$convert->convert();
	}
}

$mainClass = "WikiPathways\\GPML\\ConvertPathway";
require_once RUN_MAINTENANCE_IF_MAIN;
