<?php
/**
 * Eav.php
 *
 * @author    Kamilov Ramazan
 * @contact   ramazan@kamilov.ru
 *
 */
namespace yiinit\behaviors;

class Eav extends \CActiveRecordBehavior
{
    /**
     * тип данных: строка
     */
    const TYPE_STRING = 'string';

    /**
     * тип данных: текст
     */
    const TYPE_TEXT   = 'text';

    /**
     * тип данных: число
     */
    const TYPE_INT    = 'int';

    /**
     * тип данных: число с точкой
     */
    const TYPE_FLOAT  = 'float';

    /**
     * тип данных: дата и время
     */
    const TYPE_DATE   = 'date';

    /**
     * список eav аттрибутов
     * @var array
     */
    public $attributes = [];

    /**
     * постфикс для таблиц хранящих значения аттрибутов
     * %t - будет заменено на имя типа данных
     * %T - будет заменено на имя типа данных с большой буквы
     * @var string
     */
    public $tablePostfix = 'Eav%TValue';

    /**
     * список имён таблиц для хранения данных
     * @var array
     */
    public $tableNames = [];

    /**
     * список типов данных eav и их типов полей в базе данных
     * @var array
     */
    private $_fieldTypes = [
        self::TYPE_INT    => 'INT NOT NULL',
        self::TYPE_FLOAT  => 'DECIMAL(11,2) NOT NULL',
        self::TYPE_STRING => 'VARCHAR(255) NOT NULL',
        self::TYPE_TEXT   => 'TEXT NOT NULL',
        self::TYPE_DATE   => 'DATETIME NOT NULL'
    ];

    /**
     * @param \CActiveRecord $owner
     */
    public function attach($owner)
    {
        parent::attach($owner);

        foreach($this->attributes as $attribute => $type) {
            if(isset($this->_fieldTypes[$type])) {
                $owner->metaData->columns[$attribute] = $type;
            }
        }
    }

    /**
     * если в условии выбора данных не были конкретезированы выборы полей, то будет выбраные eav поля
     * @param \CEvent $event
     */
    public function beforeFind($event)
    {
        $model    = $this->owner;
        $criteria = $model->dbCriteria;

        if($criteria->select === '*') {
            $connection = $model->dbConnection;
            $primaryKey = $model->getTableAlias(true) . '.' . $connection->quoteColumnName($model->tableSchema->primaryKey);

            foreach($this->attributes as $name => $type) {
                $tableName = $this->_getTableSchema($type)->rawName;
                $attribute = $tableName . '.' . $connection->quoteColumnName('attribute');
                $item      = $tableName . '.' . $connection->quoteColumnName('item');
                $value     = $tableName . '.' . $connection->quoteColumnName('value');

                $criteria->select .= ', ' . $value . ' AS ' . $connection->quoteColumnName($name);
                $criteria->join   .= PHP_EOL . 'LEFT JOIN ' . $tableName;
                $criteria->join   .= ' ON (' . $attribute . ' = ' . $connection->quoteValue($name);
                $criteria->join   .= ' AND ' . $item . ' = ' . $primaryKey . ')';
            }
        }
        parent::beforeFind($event);
    }

    /**
     * сохраняем данные eav аттрибутов
     * @param \CEvent $event
     */
    public function afterSave($event)
    {
        $connection = $this->owner->dbConnection;
        $columns    = [
            $connection->quoteColumnName('item'),
            $connection->quoteColumnName('attribute'),
            $connection->quoteColumnName('value')
        ];
        $values     = [
            $connection->quoteValue($this->owner->primaryKey)
        ];

        foreach($this->attributes as $name => $type) {
            if(($value = $this->owner->{$name}) !== null) {
                $values[1] = $connection->quoteValue($name);
                $values[2] = $connection->quoteValue($value);

                $command = 'INSERT INTO ' . $this->_getTableSchema($type)->rawName;
                $command.= ' (' . implode(', ', $columns) . ') VALUES ( ' . implode(', ', $values) . ' ) ';
                $command.= 'ON DUPLICATE KEY UPDATE ' . $columns[2] . ' = VALUES(' . $columns[2] . ')';

                $connection->createCommand($command)->execute();
            }
        }
        parent::afterSave($event);
    }

    /**
     * возвращает объект схемы таблицы, хранязей значения указанного типа данных
     * если таблица не была найдена в бд, то она будет создана
     * @param string $type
     *
     * @return \CDbTableSchema
     */
    private function _getTableSchema($type)
    {
        if(!isset($this->tableNames[$type])) {
            $this->tableNames[$type] = $this->owner->tableSchema->name . str_replace(
                ['%T', '%t'],
                [ucfirst($type), $type],
                $this->tablePostfix
            );
        }

        $connection = $this->owner->dbConnection;

        if($connection->schema->getTable($this->tableNames[$type]) === null) {
            $command = $connection->createCommand();

            $command->createTable($this->tableNames[$type], [
                'attribute' => 'VARCHAR(35) NOT NULL',
                'item'      => $this->_getPrimaryKeyType(),
                'value'     => $this->_fieldTypes[$type]
            ]);

            $command->addPrimaryKey('PRIMARY', $this->tableNames[$type], 'attribute, item');
            $command->createIndex($this->_getForeignKeyName($type, '_idx'), $this->tableNames[$type], 'item');

            if($type !== self::TYPE_TEXT) {
                $command->createIndex('Value', $this->tableNames[$type], 'value');
            }

            $command->addForeignKey(
                $this->_getForeignKeyName($type),
                $this->tableNames[$type],
                'item',
                $this->owner->tableSchema->name,
                $this->owner->tableSchema->primaryKey,
                'CASCADE',
                'CASCADE'
            );

            $connection->schema->refresh();
        }

        return $connection->schema->getTable($this->tableNames[$type]);
    }

    /**
     * возвращает тип первичного ключа из привязываемой таблицы
     * @return string
     */
    private function _getPrimaryKeyType()
    {
        $column = $this->owner->tableSchema->getColumn($this->owner->tableSchema->primaryKey);
        $result = strtoupper($column->dbType);

        if($column->allowNull === false) {
            $result .= ' NOT';
        }

        return $result . ' NULL';
    }

    /**
     * возвращает имя внешнего ключа
     * @param string $type
     * @param string $postfix
     *
     * @return string
     */
    private function _getForeignKeyName($type, $postfix = '')
    {
        return $this->tableNames[$type] . '_' . $this->owner->tableSchema->name . $postfix;
    }
}