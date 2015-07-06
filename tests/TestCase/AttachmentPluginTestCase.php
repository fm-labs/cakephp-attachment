<?php
namespace Attachment\Test\TestCase;

use Cake\TestSuite\TestCase;
use Cake\Filesystem\Folder;

class AttachmentPluginTestCase extends TestCase
{
    /**
     * @var string Path to test files directory
     */
    public static $filesDir;

    /**
     * @var string Path to temporary test attachments directory
     */
    public static $attachmentsDir;

    public static function setUpBeforeClass()
    {
        static::$filesDir = dirname(__DIR__) . DS . '_files' . DS;
        static::$attachmentsDir = TMP . 'tests' . DS . 'attachments' . DS;

        $FilesFolder = new Folder(static::$filesDir);
        $Folder = new Folder(static::$attachmentsDir, true, 0777);

        // copy dummy files
        $FilesFolder->copy([
            'to' => $Folder->pwd(),
            'scheme' => Folder::OVERWRITE,
            'skip' => ['somefile.txt']
        ]);
    }

    public static function tearDownAfterClass()
    {
        $Folder = new Folder(static::$attachmentsDir);
        $Folder->delete();
    }

    public function getTestFilePath($filename)
    {
        return static::$filesDir . $filename;
    }

    public function getTestFileContents($filename)
    {
        return file_get_contents($this->getTestFilePath($filename));
    }

    public function testDummy()
    {
        // I'm just a dummy test method,
        // that phpunit won't throw warnings
    }
}
