<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WikiPathways\GPML\Converter;

# TODO: how should we do this properly?
if ( !function_exists("wfDebugLog") ) {
	function wfDebugLog( $msg ) {
		#echo $msg;
	}
}


final class GPMLConverterTest extends TestCase
{

    # change to true to update expected data
    private static $OVERWRITE = false;

    public function testInstantiateClass()
    {
        $this->assertInstanceOf(
            Converter::class,
	    new Converter( "WP4" )
        );
    }

    public function testGPML2PVJSON()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.gpml");
	$expected=file_get_contents(__DIR__."/../data/minimal.json");
	$this->converted = (new Converter( $identifier ))->gpml2pvjson(
		$input,
		[ "identifier" => $identifier,
		  "version" => "0",
		  "organism" => "Human" ]
	);
	if (self::$OVERWRITE) {
		file_put_contents(__DIR__."/../data/minimal.json", $this->converted);
	}
        $this->assertEquals(
	    $expected,
	    $this->converted
        );
    }

    public function testJSON2SVG()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.json");
	$expected=file_get_contents(__DIR__."/../data/minimal.svg");
	$this->converted = (new Converter( $identifier ))->getPvjson2svg(
	    $input,
	    []
	);
	if (self::$OVERWRITE) {
		file_put_contents(__DIR__."/../data/minimal.svg", $this->converted);
	}
        $this->assertEquals(
            $expected,
	    $this->converted
        );
    }

    public function testGPML2TXT()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.gpml");

	$expected=file_get_contents(__DIR__."/../data/minimal.txt");

	$this->converted = (new Converter( $identifier ))->getGpml2txt(
	    $input,
	    []
	);

	if (self::$OVERWRITE) {
		echo 'Overwriting expected result';
		file_put_contents(__DIR__."/../data/minimal.txt", $this->converted);
	}
        $this->assertEquals(
            $expected,
	    $this->converted
        );
    }

    public function testGPML2PNG()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.gpml");

	$this->converted = (new Converter( $identifier ))->getGpml2png(
	    $input,
	    []
	);

	$expected_file=__DIR__."/../data/minimal.png";
	$expected = '';
	if (file_exists($expected_file)) {
		$expected=file_get_contents($expected_file);
	} else {
		echo "The file $expected_file does not exist";
	}

	if (self::$OVERWRITE) {
		echo 'Overwriting expected result';
		file_put_contents($expected_file, $this->converted);
	}

        $this->assertEquals(
            $expected,
	    $this->converted
        );
    }

    public function testGPML2PNG200()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.gpml");

	$this->converted = (new Converter( $identifier ))->getGpml2png(
	    $input,
	    [$scale=>200]
	);

	$expected_file=__DIR__."/../data/minimal200.png";
	$expected = '';
	if (file_exists($expected_file)) {
		$expected=file_get_contents($expected_file);
	} else {
		echo "The file $expected_file does not exist";
	}

	if (self::$OVERWRITE) {
		echo 'Overwriting expected result';
		file_put_contents($expected_file, $this->converted);
	}

        $this->assertEquals(
            $expected,
	    $this->converted
        );
    }

    public function testGPML2OWL()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.gpml");

	$this->converted = (new Converter( $identifier ))->getGpml2owl(
	    $input,
	    []
	);

	$expected_file=__DIR__."/../data/minimal.owl";
	$expected = '';
	if (file_exists($expected_file)) {
		$expected=file_get_contents($expected_file);
	} else {
		echo "The file $expected_file does not exist";
	}

	if (self::$OVERWRITE) {
		echo 'Overwriting expected result';
		file_put_contents($expected_file, $this->converted);
	}

        $this->assertEquals(
            $expected,
	    $this->converted
        );
    }

    public function testGPML2PDF()
    {
	$identifier="WP4";
	$input=file_get_contents(__DIR__."/../data/minimal.gpml");

	$this->converted = (new Converter( $identifier ))->getGpml2pdf(
	    $input,
	    []
	);

	$expected_file=__DIR__."/../data/minimal.pdf";
	$expected = '';
	if (file_exists($expected_file)) {
		$expected=file_get_contents($expected_file);
	} else {
		echo "The file $expected_file does not exist";
	}

	if (self::$OVERWRITE) {
		echo 'Overwriting expected result';
		file_put_contents($expected_file, $this->converted);
	}

	# the pdf contains a timestamp or something, so it will not be
	# exactly the same
        $this->assertEquals(
            strlen($expected),
	    strlen($this->converted)
        );
    }

}
