<?php
namespace Attachment\Model\Behavior;

use ArrayObject;
use Attachment\Model\Entity\Attachment;
use Attachment\Model\Entity\AttachmentFile;
use Attachment\Model\Table\AttachmentsTable;
use Attachment\Model\Table\AttachmentsTableInterface;
use Cake\Collection\Iterator\MapReduce;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Upload\Exception\UploadException;
use Upload\Uploader;

class AttachmentBehavior extends Behavior
{
    protected $_defaultConfig = [
        'dataDir' => TMP,
        'dataUrl' => false,
        'fields' => []
    ];

    protected $_defaultFieldConfig = [
        'inline' => true,
        // Attachments table class
        'attachmentsTable' => 'Attachment.Attachments',
        // Attachment file class
        'fileClass' => '\\Attachment\\Model\\Entity\\AttachmentFile',
        // Upload config or config name
        'uploadConfig' => false,
        // Allow multiple files
        'multiple' => false,
        // Remove file on delete,
        //'removeOnDelete' => true,
    ];

    /**
     * @var array List of configured attachment fields
     */
    protected $_fields = [];

    /**
     * @var array List of files flagged for removal
     */
    protected $_flaggedForRemoval = [];

    /**
     * @param array $config
     */
    public function initialize(array $config)
    {
        foreach ($this->_config['fields'] as $field => $_config) {
            $_config = array_merge($this->_defaultFieldConfig, $_config);
            $this->_fields[$field] = $_config;
        }
    }

    public function findAttachment(Query $query, array $options)
    {
        if (!isset($options['field']) || !isset($options['id'])) {
            debug("findAttachment() option 'field' or 'id' missing");
            return $query;
        }

        $Attachments = $this->_getAttachmentsModel($options['field']);
        return $Attachments->find()->where([
            'model' => $this->_table->alias(),
            'modelid' => $options['id'],
            'scope' => $options['field'],
        ]);
    }

    public function attachFile($entity, $fieldName, $path, $attachmentData = [])
    {
        //$config = $this->_fields[$field];
        $Attachments = $this->_getAttachmentsModel($fieldName);

        $_data = [
            'model' => $this->_table->alias(),
            'modelid' => $entity->id,
            'scope' => null,
            'type' => null,
            'title' => basename($path),
            'desc' => null,
        ];
        $_data = array_merge($_data, $attachmentData);
        $attachment = $Attachments->newEntity($_data);

        // create entity from arguments
        return $this->_getAttachmentsModel($fieldName)->saveAttachment($attachment);
    }

    /**
     * @param $field
     * @return AttachmentsTable
     */
    protected function _getAttachmentsModel($field)
    {
        return TableRegistry::get($this->_fields[$field]['attachmentsTable']);
    }

    /**
     * 'beforeFind' callback
     *
     * Applies a MapReduce to the query, which resolves attachment info
     * if an attachment field is present in the query results.
     *
     * @param Event $event
     * @param Query $query
     * @param ArrayObject $options
     * @param $primary
     */
    public function beforeFind(Event $event, Query $query, ArrayObject $options, $primary)
    {
        //debug("beforeFind");
        $mapper = function ($row, $key, MapReduce $mapReduce) {

            foreach ($this->_fields as $fieldName => $field) {
                if ($field['inline'] === true && isset($row[$fieldName]) && !empty($row[$fieldName])) {
                    $row[$fieldName] = $this->_resolveInlineAttachment($row[$fieldName], $field);
                } else {
                    $row[$fieldName] = $this->_resolveDbAttachment($row, $fieldName, $field);
                }
            }

            $mapReduce->emitIntermediate($row, $key);
        };

        $reducer = function ($bucket, $name, MapReduce $mapReduce) {
            $mapReduce->emit($bucket[0], $name);
        };

        $query->mapReduce($mapper, $reducer);
    }

    /**
     * @param string $filePath Relative file path to configured dataDir
     * @param array $field Field config
     * @return array|AttachmentFile
     */
    protected function _resolveInlineAttachment($filePath, $field)
    {
        //debug("resolve inline attachment " . $this->_table->alias() . ":" . $filePath);
        $config =& $this->_config;

        //@TODO Keep it dry (possible duplicate in '_resolveDbAttachment' method)
        //@TODO Refactor resolver with Attachment entity as fileClass / file interface
        //@TODO Extract file extension
        // Resolve Inline Attachment
        $resolver = function ($filePath) use ($field, $config) {
            $sourcePath = $config['dataDir'] . $filePath;

            $file = new $field['fileClass']();
            $file->name = $filePath;
            $file->source = $sourcePath;
            $file->size = (int) @filesize($sourcePath);
            $file->basename = basename($filePath);
            $file->ext = null;

            if ($config['dataUrl']) {
                $file->url = rtrim($config['dataUrl'], '/') . '/' . $filePath;
            }

            return $file;
        };


        if ($field['multiple']) {
            $paths = explode(',', $filePath);
            $files = [];
            foreach ($paths as $_filePath) {
                $files[] = $resolver($_filePath);
            }
            return $files;

        } else {
            return $resolver($filePath);
        }

    }

    protected function _resolveDbAttachment($row, $fieldName, $field)
    {
        //debug("Resolving db attachments for field " . $fieldName);
        $Attachments = $this->_getAttachmentsModel($fieldName);
        $params = [
            'model' => $this->_table->alias(),
            'modelid' => $row->id,
            'scope' => $fieldName,
        ];
        $query = $Attachments->find()->where($params);

        //@TODO Keep it dry (possible duplicate in '_resolveInlineAttachment' method)
        //@TODO Extract file extension
        // Resolve DB Attachment
        $config =& $this->_config;
        $resolver = function ($attachment) use ($field, $config) {
            if ($attachment === null) {
                return;
            }

            $filePath = $attachment->filepath;
            $sourcePath = $config['dataDir'] . $filePath;

            $file = new $field['fileClass']();
            $file->name = $filePath;
            $file->source = $sourcePath;
            $file->size = $attachment->filesize;
            $file->basename = $attachment->filename;
            $file->ext = null;
            $file->desc = $attachment->desc_text;

            if ($config['dataUrl']) {
                $file->url = rtrim($config['dataUrl'], '/') . '/' . $filePath;
            }

            return $file;
        };

        if ($field['multiple']) {
            $attachments = $query->all();
            $files = [];
            foreach ($attachments as $attachment) {
                $files[] = $resolver($attachment);
            }
            return $files;
        } else {
            $attachment = $query->first();
            return $resolver($attachment);
        }
    }

    public function buildValidator(Event $event, Validator $validator, $name)
    {
        /*
        foreach ($this->_fields as $fieldName => $fieldConfig) {
            debug("buildValidator for field $fieldName");
            // Upload validation
            //if ($fieldConfig['uploadConfig'] === true) {
                $validator->requirePresence($fieldName);
                $validator->add($fieldName, [
                    'uploadConfig' => [
                        'rule' => 'check_upload'
                    ]
                ]);
            //}
        }
        */
        //debug("buildValidator");
    }

    public function beforeSave(Event $event, Entity $entity, ArrayObject $options)
    {
        //debug("beforeSave");
        //debug($this->_table);

        foreach ($this->_fields as $field => $fieldConfig) {
            $uploadField = $field . '_upload';
            $uploadOptions = ['exceptions' => true];
            $value = null;

            if ($fieldConfig['uploadConfig'] && $entity->dirty($uploadField)) {
                $Uploader = new Uploader($fieldConfig['uploadConfig']);

                try {

                    if ($fieldConfig['multiple']) {
                        $Uploader->config('multiple', true);
                        $value = [];
                        if ($Uploader->upload($entity->{$uploadField}, $uploadOptions)) {
                            foreach ($Uploader->getResult() as $upload) {
                                $value[] = $upload['basename'];
                            }
                        }
                        $value = join(',', $value);
                    } else {
                        if ($Uploader->upload($entity->{$uploadField}, $uploadOptions)) {
                            $value = $Uploader->getResult()['basename'];
                            $value = $this->_resolveAttachment($value, $fieldConfig);
                        }
                    }

                    // flag deprecated item for removal
                    if ($entity->$field) {
                        $this->_flaggedForRemoval[] = $entity->$field->source;
                    }

                    // replace with uploaded item
                    $entity->$field = $value;

                    // clear upload field
                    unset($entity->$uploadField);

                } catch (UploadException $ex) {
                    Log::alert('AttachmentBehavior: UploadException: ' . $ex->getMessage());
                    $entity->errors($uploadField, [$ex->getMessage()]);
                    $entity->errors($field, [$ex->getMessage()]);
                    return false;

                } catch (\Exception $ex) {
                    Log::alert('AttachmentBehavior: Exception: ' . $ex->getMessage());
                    $entity->errors($uploadField, [$ex->getMessage()]);
                    $entity->errors($field, [$ex->getMessage()]);
                    return false;
                }


            } elseif ($entity->has($field)) {
                unset($entity->$uploadField);
            }
        }

        //debug($entity);

        $this->_removeFlagged();
    }

    protected function _removeFlagged()
    {
        for ($i = 0; $i < count($this->_flaggedForRemoval); $i++) {
            $path = $this->_flaggedForRemoval[$i];
            @unlink($path);
            //debug("unlinked $path");
            unset($this->_flaggedForRemoval[$i]);
        }
    }
}
