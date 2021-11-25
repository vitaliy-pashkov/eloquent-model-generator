<?php

namespace Krlove\EloquentModelGenerator\Processor;

use Krlove\CodeGenerator\Model\ClassNameModel;
use Krlove\CodeGenerator\Model\DocBlockModel;
use Krlove\CodeGenerator\Model\PropertyModel;
use Krlove\CodeGenerator\Model\UseClassModel;
use Krlove\CodeGenerator\Model\UseTraitModel;
use Krlove\EloquentModelGenerator\Config;
use Krlove\EloquentModelGenerator\Helper\EmgHelper;
use Krlove\EloquentModelGenerator\Model\EloquentModel;

/**
 * Class TableNameProcessor
 * @package Krlove\EloquentModelGenerator\Processor
 */
class TraitProcessor implements ProcessorInterface
{
    /**
     * @var EmgHelper
     */
    protected $helper;

    /**
     * TableNameProcessor constructor.
     * @param EmgHelper $helper
     */
    public function __construct(EmgHelper $helper)
    {
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function process(EloquentModel $model, Config $config)
    {
        $className     = $config->get('class_name');
        $traitName = "\\App\\Models\\Traits\\$className";
        if(trait_exists($traitName))
        {
            var_dump('trait exists');
            $model->addTrait(new UseTraitModel($traitName) );
        }


//        $baseClassName = $config->get('base_class_name');
//        $tableName     = $config->get('table_name');
//
//        $model->setName(new ClassNameModel($className, $this->helper->getShortClassName($baseClassName)));
//        $model->addUses(new UseClassModel(ltrim($baseClassName, '\\')));
//        $model->setTableName($tableName ?: $this->helper->getDefaultTableName($className));
//
//        if ($model->getTableName() !== $this->helper->getDefaultTableName($className)) {
//            $tableName = $model->getTableName();
//            $tableName = str_replace('"','',$tableName);
//            $tableName = str_replace('\\','',$tableName);
//            $property = new PropertyModel('table', 'protected', $tableName);
//            $property->setDocBlock(new DocBlockModel('The table associated with the model.', '', '@var string'));
//            $model->addProperty($property);
//        }
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return 10;
    }
}
