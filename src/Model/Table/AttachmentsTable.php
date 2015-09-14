<?php
namespace Attachment\Model\Table;

use Attachment\Model\Entity\Attachment;
use Attachment\Model\Entity\AttachmentFile;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * Attachments Model
 *
 */
class AttachmentsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('attachments');
        $this->displayField('title');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');

    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create');

        $validator
            ->allowEmpty('model');

        $validator
            ->add('modelid', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('modelid');

        $validator
            ->allowEmpty('scope');

        $validator
            ->allowEmpty('type');

        $validator
            ->requirePresence('filepath', 'create')
            ->notEmpty('filepath');

        $validator
            ->requirePresence('filename', 'create')
            ->notEmpty('filename');

        $validator
            ->allowEmpty('title');

        $validator
            ->allowEmpty('desc');

        $validator
            ->allowEmpty('mimetype');

        $validator
            ->add('filesize', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('filesize');

        return $validator;
    }

    /**
     * @param Attachment $entity
     * @return bool|EntityInterface
     */
    public function saveAttachment(Attachment $entity)
    {
        return $this->save($entity);
    }
}
