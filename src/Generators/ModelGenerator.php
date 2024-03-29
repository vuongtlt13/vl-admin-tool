<?php

namespace Vuongdq\VLAdminTool\Generators;

use Illuminate\Support\Str;
use Vuongdq\VLAdminTool\Common\CommandData;
use Vuongdq\VLAdminTool\Common\GeneratorFieldRelation;
use Vuongdq\VLAdminTool\Repositories\DBTypeRepository;
use Vuongdq\VLAdminTool\Utils\FileUtil;
use Vuongdq\VLAdminTool\Utils\TableFieldsGenerator;

class ModelGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;
    private $fileName;
    private $table;

    /**
     * ModelGenerator constructor.
     *
     * @param CommandData $commandData
     */
    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path = $commandData->config->pathModel;
        $this->fileName = $this->commandData->modelName.'.php';
        $this->table = $this->commandData->dynamicVars['$TABLE_NAME$'];
    }

    public function generate()
    {
        if ($this->commandData->modelObject->is_authenticate) {
            $templateData = get_template('model.user_model', 'vl-admin-tool');
        } else {
            $templateData = get_template('model.base_model', 'vl-admin-tool');
        }

        $templateData = $this->fillTemplate($templateData);

        FileUtil::createFile($this->path, $this->fileName, $templateData);

        $this->commandData->commandComment("\nModels created: ");
        $this->commandData->commandInfo($this->fileName);
    }

    private function fillTemplate($templateData)
    {
        $rules = $this->generateRules();
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $templateData = $this->fillSoftDeletes($templateData);

        $fillables = [];
        $primaryKey = 'id';

        foreach ($this->commandData->fields as $field) {
            if ((!in_array($field->name, $this->commandData->timestampFields))
                && ($field->name !== $this->commandData->softDeleteField)
                && (!$field->isPrimary)
            ) {
                $fillables[] = "'".$field->name."'";
            }

            if ($field->isPrimary) {
                $primaryKey = $field->name;
            }
        }

        $templateData = $this->fillDocs($templateData);

        $templateData = $this->fillTimestamps($templateData);

        $primary = "protected \$primaryKey = '".$primaryKey."';\n";

        $templateData = str_replace('$PRIMARY$', $primary, $templateData);

        $templateData = str_replace('$FIELDS$', implode(','.infy_nl_tab(1, 2), $fillables), $templateData);

        $templateData = str_replace('$RULES$', implode(','.infy_nl_tab(1, 2), $rules), $templateData);

        $templateData = str_replace('$CAST$', implode(','.infy_nl_tab(1, 2), $this->generateCasts()), $templateData);

        $templateData = str_replace(
            '$RELATIONS$',
            infy_nl_tab(1, 1).fill_template($this->commandData->dynamicVars, implode(PHP_EOL.infy_nl_tab(1, 1), $this->generateRelations())),
            $templateData
        );

        $templateData = str_replace('$GENERATE_DATE$', date('F j, Y, g:i a T'), $templateData);

        return $templateData;
    }

    private function fillSoftDeletes($templateData)
    {
        if (!$this->commandData->isUseSoftDelete()) {
            $templateData = str_replace('$SOFT_DELETE_IMPORT$', '', $templateData);
            $templateData = str_replace('$SOFT_DELETE$', '', $templateData);
            $templateData = str_replace('$SOFT_DELETE_DATES$', '', $templateData);
            $templateData = str_replace('$SOFT_DELETE_COLUMN$', '', $templateData);
        } else {
            $templateData = str_replace(
                '$SOFT_DELETE_IMPORT$',
                "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n",
                $templateData
            );
            $templateData = str_replace('$SOFT_DELETE$', "use SoftDeletes;\n", $templateData);
            $deletedAtTimestamp = config('vl_admin_tool.timestamps.deleted_at', 'deleted_at');
            $templateData = str_replace(
                '$SOFT_DELETE_DATES$',
                infy_nl_tab()."protected \$dates = ['".$deletedAtTimestamp."'];\n",
                $templateData
            );
            $templateData = str_replace(
                '$SOFT_DELETE_COLUMN$',
                infy_nl_tab()."const DELETED_AT = '$deletedAtTimestamp';\n",
                $templateData
            );
        }

        return $templateData;
    }

    private function fillDocs($templateData)
    {
        if ($this->commandData->getAddOn('swagger')) {
            $templateData = $this->generateSwagger($templateData);
        }

        $docsTemplate = get_template('docs.model', 'vl-admin-tool');
        $docsTemplate = fill_template($this->commandData->dynamicVars, $docsTemplate);

        $fillables = '';
        $fieldsArr = [];
        $count = 1;

        if (!empty($this->commandData->relations)) {
            foreach ($this->commandData->relations as $relation) {
                $field = $relationText = (isset($relation->inputs[0])) ? $relation->inputs[0] : null;
                if (in_array($field, $fieldsArr)) {
                    $relationText = $relationText . '_' . $count;
                    $count++;
                }

                $fillables .= ' * @property ' . $this->getPHPDocType($relation->type, $relation, $relationText) . PHP_EOL;
                $fieldsArr[] = $field;
            }
        }

        foreach ($this->commandData->fields as $field) {
            $fillables .= ' * @property '.$this->getPHPDocType($field->fieldType).' $'.$field->name.PHP_EOL;
        }
        $docsTemplate = str_replace('$GENERATE_DATE$', date('F j, Y, g:i a T'), $docsTemplate);
        $docsTemplate = str_replace('$PHPDOC$', $fillables, $docsTemplate);

        $templateData = str_replace('$DOCS$', $docsTemplate, $templateData);

        return $templateData;
    }

    /**
     * @param $db_type
     * @param GeneratorFieldRelation|null $relation
     * @param string|null                 $relationText
     *
     * @return string
     */
    private function getPHPDocType($db_type, $relation = null, $relationText = null)
    {
        $relationText = (!empty($relationText)) ? $relationText : null;

        switch ($db_type) {
            case 'datetime':
                return 'string|\Carbon\Carbon';
            case '1-1i':
            case '1-1':
                return '\\'.$this->commandData->config->nsModel.'\\'.$relation->inputs[0].' $'.Str::camel($relationText);
            case 'n-1':
                if (isset($relation->inputs[1])) {
                    $relationName = str_replace('_id', '', strtolower($relation->inputs[1]));
                } else {
                    $relationName = $relationText;
                }

                return '\\'.$this->commandData->config->nsModel.'\\'.$relation->inputs[0].' $'.Str::camel($relationName);
            case '1-n':
            case 'm-n':
            case 'hmt':
                return '\Illuminate\Database\Eloquent\Collection $'.Str::camel(Str::plural($relationText));
            default:
                $fieldData = SwaggerGenerator::getFieldType($db_type);
                if (!empty($fieldData['fieldType'])) {
                    return $fieldData['fieldType'];
                }

                return $db_type;
        }
    }

    public function generateSwagger($templateData)
    {
        $fieldTypes = SwaggerGenerator::generateTypes($this->commandData->fields);

        $template = get_template('model_docs.model', 'swagger-generator');

        $template = fill_template($this->commandData->dynamicVars, $template);

        $template = str_replace(
            '$REQUIRED_FIELDS$',
            '"'.implode('"'.', '.'"', $this->generateRequiredFields()).'"',
            $template
        );

        $propertyTemplate = get_template('model_docs.property', 'swagger-generator');

        $properties = SwaggerGenerator::preparePropertyFields($propertyTemplate, $fieldTypes);

        $template = str_replace('$PROPERTIES$', implode(",\n", $properties), $template);

        $templateData = str_replace('$DOCS$', $template, $templateData);

        return $templateData;
    }

    private function generateRequiredFields()
    {
        $requiredFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!empty($field->validations)) {
                if (Str::contains($field->validations, 'required')) {
                    $requiredFields[] = $field->name;
                }
            }
        }

        return $requiredFields;
    }

    private function fillTimestamps($templateData)
    {
        $isUseTimestamps = $this->commandData->isUseTimestamps();
        $timestamps = (new TableFieldsGenerator($this->commandData->modelObject))->getTimestampFieldNames();
        $replace = '';
        if (!$isUseTimestamps) {
            $replace = infy_nl_tab()."public \$timestamps = false;\n";
            $templateData = str_replace('$USE_TIMESTAMPS$', $replace, $templateData);
            return str_replace('$TIMESTAMPS$', "", $templateData);
        } else {
            $templateData = str_replace('$USE_TIMESTAMPS$', $replace, $templateData);

            list($created_at, $updated_at) = collect($timestamps)->map(function ($field) {
                return !empty($field) ? "'$field'" : 'null';
            });
            $replace = infy_nl_tab()."const CREATED_AT = $created_at;";
            $replace .= infy_nl_tab()."const UPDATED_AT = $updated_at;\n";

            return str_replace('$TIMESTAMPS$', $replace, $templateData);
        }
    }

    public function generateRules(string $type = 'create')
    {
        $tableGenerator = new TableFieldsGenerator($this->commandData->modelObject);
        $timestampFields =  $tableGenerator->getTimestampFieldNames();
        $softDeleteField = $tableGenerator->getSoftDeleteFieldName();

        $dont_require_fields = array_merge(
            $timestampFields,
            (is_null($softDeleteField) ? [] : [$softDeleteField]),
            [$tableGenerator->getPrimaryKeyOfTable()]
        );

        $rules = [];

        foreach ($this->commandData->fields as $field) {
            if (!$field->isPrimary && !in_array($field->name, $dont_require_fields)) {
                if ($field->isNotNull && empty($field->validations)) {
                    $field->validations = 'required';
                }
            }

            if (!empty($field->validations)) {
                $hasUnique = false;
                if (Str::contains($field->validations, 'unique:')) {
                    $rule = array_unique(explode('|', $field->validations));
                    // move unique rule to last
                    usort($rule, function ($record) {
                        return (Str::contains($record, 'unique:')) ? 1 : 0;
                    });
                    $hasUnique = true;
                    $field->validations = implode('|', $rule);
                }
                $rule = "'".$field->name."' => '".$field->validations."'";
                if ($hasUnique && $type == "update") {
                    $rule .= " . (\$id ? \",\$id\": \"\")";
                };
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    public function generateUniqueRules()
    {
        $tableNameSingular = Str::singular($this->commandData->config->tableName);
        $uniqueRules = '';
        foreach ($this->generateRules() as $rule) {
            if (Str::contains($rule, 'unique:')) {
                $rule = explode('=>', $rule);
                $string = '$rules['.trim($rule[0]).'].","';

                $uniqueRules .= '$rules['.trim($rule[0]).'] = '.$string.'.$this->route("'.$tableNameSingular.'");';
            }
        }

        return $uniqueRules;
    }

    public function generateCasts()
    {
        $casts = [];

        $timestamps = (new TableFieldsGenerator($this->commandData->modelObject))->getTimestampFieldNames();

        foreach ($this->commandData->fields as $field) {
            if (in_array($field->name, $timestamps)) {
                continue;
            }

            $rule = "'".$field->name."' => ";
            $castType = app(DBTypeRepository::class)->getCastsTypeByDBType($field->fieldType);
            if ($castType == 'decimal')
                $rule .= sprintf("'decimal:%d'", $field->numberDecimalPoints);
            else $rule .= "'$castType'";

            if (!empty($rule)) {
                $casts[] = $rule;
            }
        }

        return $casts;
    }

    private function generateRelations()
    {
        $relations = [];

        $count = 1;
        $fieldsArr = [];
        if (!empty($this->commandData->relations)) {
            foreach ($this->commandData->relations as $relation) {
                $field = (isset($relation->inputs[0])) ? $relation->inputs[0] : null;

                $relationShipText = $field;
                if (in_array($field, $fieldsArr)) {
                    $relationShipText = $relationShipText . '_' . $count;
                    $count++;
                }

                $relationText = $relation->getRelationFunctionText($relationShipText);
                if (!empty($relationText)) {
                    $fieldsArr[] = $field;
                    $relations[] = $relationText;
                }
            }
        }

        return $relations;
    }

    public function rollback()
    {
        if ($this->rollbackFile($this->path, $this->fileName)) {
            $this->commandData->commandComment('Models file deleted: '.$this->fileName);
        }
    }

    public function delete()
    {
        return $this->rollback();
    }
}
