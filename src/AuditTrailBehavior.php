<?php
namespace bedezign\yii2\audit;

use Yii;
use yii\base\Exception;
use yii\db\ActiveRecord;

use bedezign\yii2\audit\models\AuditTrail;
use yii\web\Application;

/**
 * Class AuditTrailBehavior
 * @package bedezign\yii2\audit
 *
 * @property \yii\db\ActiveRecord $owner
 */
class AuditTrailBehavior extends \yii\base\Behavior
{

    /**
     * Array with fields to save
     * You don't need to configure both `allowed` and `ignored`
     * @var array
     */
    public $allowed = [];

    /**
     * Array with fields to ignore
     * You don't need to configure both `allowed` and `ignored`
     * @var array
     */
    public $ignored = [];

    /**
     * Array with classes to ignore
     * @var array
     */
    public $ignoredClasses = [];

    /**
     * Is the behavior is active or not
     * @var boolean
     */
    public $active = true;

    /**
     * Date format to use in stamp - set to "Y-m-d H:i:s" for datetime or "U" for timestamp
     * @var string
     */
    public $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var array
     */
    private $_oldAttributes = [];

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterInsert',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterUpdate',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }

    /**
     *
     */
    public function afterFind()
    {
        $this->setOldAttributes($this->owner->getAttributes());
    }

    /**
     *
     */
    public function afterInsert()
    {
        $this->audit('CREATE');
        $this->setOldAttributes($this->owner->getAttributes());
    }

    /**
     *
     */
    public function afterUpdate()
    {
        $this->audit('UPDATE');
        $this->setOldAttributes($this->owner->getAttributes());
    }

    /**
     *
     */
    public function afterDelete()
    {
        $this->audit('DELETE');
        $this->setOldAttributes([]);
    }

    /**
     * @param $action
     * @throws \yii\db\Exception
     */
    public function audit($action)
    {
        // Not active? get out of here
        if (!$this->active) {
            return;
        }
        // Lets check if the whole class should be ignored
        if (sizeof($this->ignoredClasses) > 0 && array_search(get_class($this->owner), $this->ignoredClasses) !== false) {
            return;
        }
        // If this is a delete then just write one row and get out of here
        if ($action == 'DELETE') {
            $this->saveAuditTrailDelete();
            return;
        }
        // Now lets actually write the attributes
        try {
            $this->auditAttributes($action);
        } catch (\Exception $exception) {
            // do nothing - something broke.
        }
    }

    /**
     * Clean attributes of fields that are not allowed or ignored.
     *
     * @param $attributes
     * @return mixed
     */
    protected function cleanAttributes($attributes)
    {
        $attributes = $this->cleanAttributesAllowed($attributes);
        $attributes = $this->cleanAttributesIgnored($attributes);
        return $attributes;
    }

    /**
     * Unset attributes which are not allowed
     *
     * @param $attributes
     * @return mixed
     */
    protected function cleanAttributesAllowed($attributes)
    {
        if (sizeof($this->allowed) > 0) {
            foreach ($attributes as $f => $v) {
                if (array_search($f, $this->allowed) === false) {
                    unset($attributes[$f]);
                }
            }
        }
        return $attributes;
    }

    /**
     * Unset attributes which are ignored
     *
     * @param $attributes
     * @return mixed
     */
    protected function cleanAttributesIgnored($attributes)
    {
        if (sizeof($this->ignored) > 0) {
            foreach ($attributes as $f => $v) {
                if (array_search($f, $this->ignored) !== false) {
                    unset($attributes[$f]);
                }
            }
        }
        return $attributes;
    }

    /**
     * @param string $action
     * @throws \yii\db\Exception
     */
    protected function auditAttributes($action)
    {
        // Get the new and old attributes
        $newAttributes = $this->cleanAttributes($this->owner->getAttributes());
        $oldAttributes = $this->cleanAttributes($this->getOldAttributes());
        // If no difference then get out of here
        if (count(array_diff_assoc($newAttributes, $oldAttributes)) <= 0) {
            return;
        }
        // Get the trail data
        $entry_id = $this->getAuditEntryId();
        $user_id = $this->getUserId();
        $model = $this->owner->className();
        $model_id = $this->getNormalizedPk();
        $created = date($this->dateFormat);

        $this->saveAuditTrail($action, $newAttributes, $oldAttributes, $entry_id, $user_id, $model, $model_id, $created);
    }

    /**
     * Save the audit trails for a create or update action
     *
     * @param $action
     * @param $newAttributes
     * @param $oldAttributes
     * @param $entry_id
     * @param $user_id
     * @param $model
     * @param $model_id
     * @param $created
     * @throws \yii\db\Exception
     */
    protected function saveAuditTrail($action, $newAttributes, $oldAttributes, $entry_id, $user_id, $model, $model_id, $created)
    {
        // Build a list of fields to log
        $rows = array();
        foreach ($newAttributes as $field => $new) {
            $old = isset($oldAttributes[$field]) ? $oldAttributes[$field] : '';
            // If they are not the same lets write an audit log
            if ($new != $old) {
                $rows[] = [$entry_id, $user_id, $old, $new, $action, $model, $model_id, $field, $created];
            }
        }
        // Record the field changes with a batch insert
        if (!empty($rows)) {
            $columns = ['entry_id', 'user_id', 'old_value', 'new_value', 'action', 'model', 'model_id', 'field', 'created'];
            Yii::$app->db->createCommand()->batchInsert(AuditTrail::tableName(), $columns, $rows)->execute();
        }
    }

    /**
     * Save the audit trails for a delete action
     */
    protected function saveAuditTrailDelete()
    {
        Yii::$app->db->createCommand()->insert(AuditTrail::tableName(), [
            'action' => 'DELETE',
            'entry_id' => $this->getAuditEntryId(),
            'user_id' => $this->getUserId(),
            'model' => $this->owner->className(),
            'model_id' => $this->getNormalizedPk(),
            'created' => date($this->dateFormat),
        ])->execute();
    }

    /**
     * @return array
     */
    public function getOldAttributes()
    {
        return $this->_oldAttributes;
    }

    /**
     * @param $value
     */
    public function setOldAttributes($value)
    {
        $this->_oldAttributes = $value;
    }

    /**
     * @return string
     */
    protected function getNormalizedPk()
    {
        $pk = $this->owner->getPrimaryKey();
        return is_array($pk) ? json_encode($pk) : $pk;
    }

    /**
     * @return int|null|string
     */
    protected function getUserId()
    {
        return (Yii::$app instanceof Application && Yii::$app->user) ? Yii::$app->user->id : null;
    }

    /**
     * @return models\AuditEntry|null|static
     * @throws \Exception
     */
    protected function getAuditEntryId()
    {
        $module = Audit::getInstance();
        if (!$module) {
            $module = \Yii::$app->getModule(Audit::findModuleIdentifier());
        }
        if (!$module) {
            throw new \Exception('Audit module cannot be loaded');
        }
        return Audit::getInstance()->getEntry(true)->id;
    }

}
