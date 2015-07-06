<?php
namespace Attachment\Test\TestCase\Model\Behavior;

use Attachment\Test\TestCase\AttachmentPluginTestCase;
use Attachment\Model\Entity\AttachmentFile;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class AttachmentBehaviorTest extends AttachmentPluginTestCase
{
    /**
     * fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.attachment.attachment_files'
    ];

    /**
     * @var Table
     */
    public $table;

    /**
     * @var
     */
    public $testStorageDir;

    public function setUp()
    {
        parent::setUp();

        $this->table = TableRegistry::get('AttachmentFiles');
        $this->table->primaryKey(['id']);
        $this->table->entityClass('\\Attachment\\Test\\TestCase\\Model\\Entity\\ExampleEntity');
        $this->table->addBehavior('Attachment.Attachment', [
            'storageDir' => static::$attachmentsDir,
            'fields' => [
                'file' => [

                ],
                'files' => [
                    'multiple' => true,
                ]
            ]
        ]);
        //$this->table->validator();
    }

    public function tearDown()
    {
        parent::tearDown();
        TableRegistry::clear();
    }

    public function _testTestSetup()
    {
        $this->assertTrue(is_file($this->getTestFilePath('attachment01.txt')));
        $this->assertEquals('Test', $this->getTestFileContents('attachment01.txt'));
    }

    public function _testGetEntityWithAttachmentFile()
    {
        $entity = $this->table->get(1);

        $this->assertArrayHasKey('file', $entity);
        $this->assertInstanceOf('\\Attachment\\Model\\Entity\\AttachmentFile', $entity['file']);

        $this->assertEquals((string)$entity['file'], $entity['file']['source']);
        $this->assertEquals((string)$entity->file, $entity['file']->source);
    }

    public function _testGetEntityWithMultipleAttachmentFiles()
    {
        $entity = $this->table->get(4);

        $this->assertArrayHasKey('files', $entity);
        $this->assertInternalType('array', $entity['files']);
        $this->assertEquals(2, count($entity['files']));

        $this->assertInstanceOf('\\Attachment\\Model\\Entity\\AttachmentFile', $entity['files'][0]);
        $this->assertInstanceOf('\\Attachment\\Model\\Entity\\AttachmentFile', $entity['files'][1]);

    }

    public function testCreateEntityWithAttachmentFile()
    {
        $entity = $this->table->newEntity();
        $entity->title = 'Test Create';
        //$entity->file = AttachmentFile::import($this->getTestFilePath('attachment03.txt'));

        if ($entity->errors()) {
            debug($entity->errors());
        }

        $this->table->save($entity);
        $this->assertNotEmpty($entity['id']);

        // @todo clear cache

        //$entity = $this->table->get($entity['id']);
        //debug($entity);
        //$this->assertEquals('attachment03.txt', (string) $entity['file']);
    }

}
