<?php
namespace Attachment\Model\Behavior;

use ArrayObject;
use Attachment\Model\Entity\AttachmentFile;
use Cake\Collection\Iterator\MapReduce;
use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Query;
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
        // Attachment file class
        'attachmentClass' => '\\Attachment\\Model\\Entity\\AttachmentFile',
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
                if (isset($row[$fieldName]) && !empty($row[$fieldName])) {
                    $row[$fieldName] = $this->_resolveAttachment($row[$fieldName], $field);
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
     * @param $fileName
     * @param $field
     * @return array|AttachmentFile
     */
    protected function _resolveAttachment($fileName, $field)
    {
        $config =& $this->_config;
        $resolver = function ($fileName) use ($field, $config) {
            $sourcePath = $config['dataDir'] . $fileName;

            $attachment = new $field['attachmentClass']();
            $attachment->name = $fileName;
            $attachment->source = $sourcePath;
            $attachment->size = 0; //@filesize($sourcePath);
            $attachment->basename = '';
            $attachment->ext = '';

            if ($config['dataUrl']) {
                $attachment->url = rtrim($config['dataUrl'], '/') . '/' . $fileName;
            }

            return $attachment;
        };

        if ($field['multiple']) {
            $files = explode(',', $fileName);
            $attachments = [];
            foreach ($files as $fileName) {
                $attachments[] = $resolver($fileName);
            }
            return $attachments;

        } else {
            return $resolver($fileName);
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
