<?php

namespace Krlove\EloquentModelGenerator\Processor;

use Doctrine\DBAL\Schema\Table;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Support\Str;
use Krlove\CodeGenerator\Model\DocBlockModel;
use Krlove\CodeGenerator\Model\MethodModel;
use Krlove\CodeGenerator\Model\VirtualPropertyModel;
use Krlove\EloquentModelGenerator\Config;
use Krlove\EloquentModelGenerator\Exception\GeneratorException;
use Krlove\EloquentModelGenerator\Helper\EmgHelper;
use Krlove\EloquentModelGenerator\Model\BelongsTo;
use Krlove\EloquentModelGenerator\Model\BelongsToMany;
use Krlove\EloquentModelGenerator\Model\EloquentModel;
use Krlove\EloquentModelGenerator\Model\HasMany;
use Krlove\EloquentModelGenerator\Model\HasOne;
use Krlove\EloquentModelGenerator\Model\Relation;

/**
 * Class RelationProcessor
 * @package Krlove\EloquentModelGenerator\Processor
 */
class RelationProcessor implements ProcessorInterface
{
    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var EmgHelper
     */
    protected $helper;

    /**
     * FieldProcessor constructor.
     * @param DatabaseManager $databaseManager
     * @param EmgHelper $helper
     */
    public function __construct(DatabaseManager $databaseManager, EmgHelper $helper)
    {
        $this->databaseManager = $databaseManager;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        $schemaManager = $this->databaseManager->connection($config->get('connection'))->getDoctrineSchemaManager();
        $prefix = $this->databaseManager->connection($config->get('connection'))->getTablePrefix();

        $foreignKeys = $schemaManager->listTableForeignKeys($prefix . $model->getTableName());
        foreach ($foreignKeys as $tableForeignKey) {
            $tableForeignColumns = $tableForeignKey->getForeignColumns();
            if (count($tableForeignColumns) !== 1) {
                continue;
            }

            $relation = new BelongsTo($this->removePrefix($prefix, $tableForeignKey->getForeignTableName()),
                $tableForeignKey->getLocalColumns()[0], $tableForeignColumns[0]);
            $this->addRelation($model, $relation);
        }

        $tables = $schemaManager->listTables();
        foreach ($tables as $table) {
            if ($table->getName() === $prefix . $model->getTableName()) {
                continue;
            }

            $foreignKeys = $table->getForeignKeys();
            foreach ($foreignKeys as $name => $foreignKey) {
//                var_dump($foreignKey->getForeignTableName());
//                var_dump($prefix . $model->getTableName());
                $tableName = $prefix . $model->getTableName();
                $tableName = str_replace('"', '', $tableName);

                $relationTableName = $foreignKey->getForeignTableName();
                $relationTableName = str_replace('"', '', $relationTableName);
                if ($relationTableName === $tableName) {
//                    var_dump($foreignKey->getForeignTableName());
                    $localColumns = $foreignKey->getLocalColumns();
//                    var_dump($localColumns);
                    if (count($localColumns) !== 1) {
                        continue;
                    }
//                    var_dump(count($foreignKeys) === 2 && count($table->getColumns()) === 2);

                    $localTable = $foreignKey->getLocalTable();
//                    var_dump($foreignKey->getLocalTableName());
//                    var_dump(array_flip($localTable->getPrimaryKey()->getColumns()));

                    $localColumn = $foreignKey->getLocalColumns()[0];
                    $localColumn = str_replace('"', '', $localColumn);
//                    var_dump($localColumn);

                    $localPks = array_flip($localTable->getPrimaryKey()->getColumns());

                    if (array_key_exists($localColumn, $localPks) && count($localPks) === 2) {
                        $keys = array_keys($foreignKeys);
                        $key = array_search($name, $keys) === 0 ? 1 : 0;
                        $secondForeignKey = $foreignKeys[$keys[$key]];
                        $secondForeignTable = $this->removePrefix($prefix, $secondForeignKey->getForeignTableName());

                        $relation = new BelongsToMany($secondForeignTable, $this->removePrefix($prefix, $table->getName()),
                            $localColumns[0], $secondForeignKey->getLocalColumns()[0]);
                        $this->addRelation($model, $relation);

//                        break;
                    }
                    $tableName = $this->removePrefix($prefix, $foreignKey->getLocalTableName());
                    $foreignColumn = $localColumns[0];
                    $localColumn = $foreignKey->getForeignColumns()[0];
                    if ($this->isColumnUnique($table, $foreignColumn)) {
                        $relation = new HasOne($tableName, $foreignColumn, $localColumn);
                    } else {
                        $relation = new HasMany($tableName, $foreignColumn, $localColumn);
                    }
                    $this->addRelation($model, $relation);

                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 5;
    }

    /**
     * @param Table $table
     * @param string $column
     * @return bool
     */
    protected function isColumnUnique(Table $table, $column)
    {
        foreach ($table->getIndexes() as $index) {
            $indexColumns = $index->getColumns();
            if (count($indexColumns) !== 1) {
                continue;
            }
            $indexColumn = $indexColumns[0];
            if ($indexColumn === $column && $index->isUnique()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param EloquentModel $model
     * @param Relation $relation
     * @throws GeneratorException
     */
    protected function addRelation(EloquentModel $model, Relation $relation)
    {
        $relationClass = Str::singular(Str::studly($relation->getTableName()));


//        var_dump($relation->getForeignColumnName());die;
//        var_dump($relation);
//        var_dump($relationName);
//        $relationName = lcfirst(Str::camel(BaseInflector::singular($relationName)));


        if ($relation instanceof HasOne) {
            $relationName = $relation->getTableName();
            $relationName = str_replace('"', '', $relationName);
            $relationName = lcfirst(Str::camel(Str::singular($relationName)));

            $name = $relationName;
            $docBlock = sprintf('@return \%s', EloquentHasOne::class);

            $virtualPropertyType = $relationClass;
        } elseif ($relation instanceof HasMany) {
            $relationName = $relation->getTableName();
            $relationName = str_replace('"', '', $relationName);
            $relationName = lcfirst(Str::camel(Str::plural($relationName)));
//            var_dump($relationName);

            $name = $relationName;
            $methods = $model->getMethods();
            $hasRelation = false;
            foreach ($methods as $method) {
                if ($method->getName() === $name) {
                    $hasRelation = true;
                }
            }
            if ($hasRelation === true) {
                $foreignColumnName = $relation->getForeignColumnName();
                $foreignColumnName = str_replace('"', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/_id$/', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/Id$/', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/_Id$/', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/_ID$/', '', $foreignColumnName);
                $name = $relationName . ucfirst($foreignColumnName);
            }


            $docBlock = sprintf('@return \%s', EloquentHasMany::class);

            $virtualPropertyType = sprintf('%s[]', $relationClass);
        } elseif ($relation instanceof BelongsTo) {
            $relationName = $relation->getForeignColumnName();
            $relationName = str_replace('"', '', $relationName);
            $relationName = preg_replace('/_id$/', '', $relationName);
            $relationName = preg_replace('/Id$/', '', $relationName);
            $relationName = preg_replace('/_Id$/', '', $relationName);
            $relationName = preg_replace('/_ID$/', '', $relationName);

            $name = $relationName;
            $docBlock = sprintf('@return \%s', EloquentBelongsTo::class);

            $virtualPropertyType = $relationClass;
        } elseif ($relation instanceof BelongsToMany) {
            $relationName = $relation->getLocalColumnName();
//            var_dump($relationName);
            $relationName = str_replace('"', '', $relationName);
            $relationName = preg_replace('/_id$/', '', $relationName);
            $relationName = preg_replace('/Id$/', '', $relationName);
            $relationName = preg_replace('/_Id$/', '', $relationName);
            $relationName = preg_replace('/_ID$/', '', $relationName);
            $relationName = lcfirst(Str::camel(Str::plural($relationName)));

//            var_dump($relationName);die;
            $name = $relationName;
            $methods = $model->getMethods();
            $hasRelation = false;
            foreach ($methods as $method) {
                if ($method->getName() === $name) {
                    $hasRelation = true;
                }
            }
            if ($hasRelation === true) {
                $foreignColumnName = $relation->getForeignColumnName();
                $foreignColumnName = str_replace('"', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/_id$/', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/Id$/', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/_Id$/', '', $foreignColumnName);
                $foreignColumnName = preg_replace('/_ID$/', '', $foreignColumnName);
                $name = $relationName . ucfirst($foreignColumnName);
            }

            $docBlock = sprintf('@return \%s', EloquentBelongsToMany::class);

            $virtualPropertyType = sprintf('%s[]', $relationClass);
        } else {
            throw new GeneratorException('Relation not supported');
        }

//        var_dump($name);
        $method = new MethodModel($name);
        $method->setBody($this->createMethodBody($model, $relation));
        $method->setDocBlock(new DocBlockModel($docBlock));

        $model->addMethod($method);
        $model->addProperty(new VirtualPropertyModel($name, $virtualPropertyType));
    }

    /**
     * @param EloquentModel $model
     * @param Relation $relation
     * @return string
     */
    protected function createMethodBody(EloquentModel $model, Relation $relation)
    {
        $reflectionObject = new \ReflectionObject($relation);
        $name = Str::camel($reflectionObject->getShortName());

        $arguments = [
            $model->getNamespace()->getNamespace() . '\\' . Str::singular(Str::studly($relation->getTableName())),
        ];

        if ($relation instanceof BelongsToMany) {
            $defaultJoinTableName = $this->helper->getDefaultJoinTableName($model->getTableName(), $relation->getTableName());
            $joinTableName = $relation->getJoinTable() === $defaultJoinTableName ? null : $relation->getJoinTable();
            $arguments[] = $joinTableName;

            $arguments[] = $this->resolveArgument($relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($model->getTableName()));
            $arguments[] = $this->resolveArgument($relation->getLocalColumnName(),
                $this->helper->getDefaultForeignColumnName($relation->getTableName()));
        } elseif ($relation instanceof HasMany) {
            $arguments[] = $this->resolveArgument($relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($model->getTableName()));
            $arguments[] = $this->resolveArgument($relation->getLocalColumnName(), EmgHelper::DEFAULT_PRIMARY_KEY);
        } else {
            $arguments[] = $this->resolveArgument($relation->getForeignColumnName(),
                $this->helper->getDefaultForeignColumnName($relation->getTableName()));
            $arguments[] = $this->resolveArgument($relation->getLocalColumnName(), EmgHelper::DEFAULT_PRIMARY_KEY);
        }

        return sprintf('return $this->%s(%s);', $name, $this->prepareArguments($arguments));
    }

    /**
     * @param array $array
     * @return array
     */
    protected function prepareArguments(array $array)
    {
        $array = array_reverse($array);
        $milestone = false;
        foreach ($array as $key => &$item) {
            if (!$milestone) {
                if (!is_string($item)) {
                    unset($array[$key]);
                } else {
                    $milestone = true;
                }
            } else {
                if ($item === null) {
                    $item = 'null';

                    continue;
                }
            }
            $item = sprintf("'%s'", $item);
        }

        return implode(', ', array_reverse($array));
    }

    /**
     * @param string $actual
     * @param string $default
     * @return string|null
     */
    protected function resolveArgument($actual, $default)
    {
        return $actual === $default ? null : $actual;
    }

    /**
     * todo: move to helper
     * @param string $prefix
     * @param string $tableName
     * @return string
     */
    protected function addPrefix($prefix, $tableName)
    {
        return $prefix . $tableName;
    }

    /**
     * todo: move to helper
     * @param string $prefix
     * @param string $tableName
     * @return string
     */
    protected function removePrefix($prefix, $tableName)
    {
        $prefix = preg_quote($prefix, '/');

        return preg_replace("/^$prefix/", '', $tableName);
    }
}
