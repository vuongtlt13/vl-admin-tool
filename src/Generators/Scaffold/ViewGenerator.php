<?php

namespace Vuongdq\VLAdminTool\Generators\Scaffold;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Vuongdq\VLAdminTool\Common\CommandData;
use Vuongdq\VLAdminTool\Generators\BaseGenerator;
use Vuongdq\VLAdminTool\Generators\ViewServiceProviderGenerator;
use Vuongdq\VLAdminTool\Utils\FileUtil;
use Vuongdq\VLAdminTool\Utils\HTMLFieldGenerator;

class ViewGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;

    /** @var string */
    private $templateType;

    /** @var array */
    private $htmlFields;

    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path = $commandData->config->pathViews;
        $this->templateType = config('vl_admin_tool.templates', 'adminlte-templates');
    }

    public function generate()
    {
        if (!file_exists($this->path)) {
            mkdir($this->path, 0755, true);
        }

        $htmlInputs = Arr::pluck($this->commandData->fields, 'htmlInput');
        if (in_array('file', $htmlInputs)) {
            $this->commandData->addDynamicVariable('$FILES$', ", 'files' => true");
        }

        $this->commandData->commandComment("\nGenerating Views...");

        if ($this->commandData->getOption('views')) {
            $viewsToBeGenerated = explode(',', $this->commandData->getOption('views'));

            if (in_array('index', $viewsToBeGenerated)) {
                $this->generateTable();
                $this->generateTableTypes();
                $this->generateIndex();
            }

            if (count(array_intersect(['create', 'update'], $viewsToBeGenerated)) > 0) {
                $this->generateFields();
            }

            if (in_array('create', $viewsToBeGenerated)) {
                $this->generateCreate();
            }

            if (in_array('edit', $viewsToBeGenerated)) {
                $this->generateUpdate();
            }

        } else {
            $this->generateTable();
            $this->generateTableTypes();
            $this->generateIndex();
            $this->generateFields();
            $this->generateCreate();
            $this->generateUpdate();
        }

        $this->commandData->commandComment('Views created: ');
    }

    private function generateTable()
    {
        $this->generateDataTableToolbar();
        $templateData = $this->generateDataTableBody();
        $this->generateDataTableActions();

        FileUtil::createFile($this->path, 'table.blade.php', $templateData);

        $this->commandData->commandInfo('table.blade.php created');
    }

    private function generateDataTableToolbar() {
        $templateData = get_template('views.toolbar', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'toolbar.blade.php', $templateData);

        $this->commandData->commandInfo('toolbar.blade.php created');

        // js file
        $templateData = get_template('views.toolbar_js', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'toolbar_js.blade.php', $templateData);

        $this->commandData->commandInfo('toolbar_js.blade.php created');
    }

    private function generateTableTypes() {
        $templateData = get_template('views.table_with_crud_modals', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'table_with_crud_modals.blade.php', $templateData);

        $this->commandData->commandInfo('table_with_crud_modals.blade.php created');

        // only view
        $templateData = get_template('views.table_only_view', $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'table_only_view.blade.php', $templateData);

        $this->commandData->commandInfo('table_only_view.blade.php created');
    }

    private function generateDataTableBody()
    {
        $templateData = get_template('views.datatable_body', $this->templateType);

        return fill_template($this->commandData->dynamicVars, $templateData);
    }

    private function generateDataTableActions()
    {
        $templateName = 'datatables_actions';

        $templateData = get_template('views.'.$templateName, $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'datatables_actions.blade.php', $templateData);

        $this->commandData->commandInfo('datatables_actions.blade.php created');
    }

    private function generateIndex()
    {
        $templateName = 'index';

        $templateData = get_template('views.'.$templateName, $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $templateData = str_replace('$PAGINATE$', '', $templateData);

        FileUtil::createFile($this->path, 'index.blade.php', $templateData);

        $this->commandData->commandInfo('index.blade.php created');
    }

    private function generateFields()
    {
        $templateName = 'fields';

        $this->htmlFields = [];

        foreach ($this->commandData->fields as $field) {
            if (!$field->isCreatable && !$field->isEditable) {
                continue;
            }

            $validations = explode('|', $field->validations);
            $minMaxRules = '';
            foreach ($validations as $validation) {
                if (!Str::contains($validation, ['max:', 'min:'])) {
                    continue;
                }

                $validationText = substr($validation, 0, 3);
                $sizeInNumber = substr($validation, 4);

                $sizeText = ($validationText == 'min') ? 'minlength' : 'maxlength';
                if ($field->htmlType == 'number') {
                    $sizeText = $validationText;
                }

                $size = ",'$sizeText' => $sizeInNumber";
                $minMaxRules .= $size;
            }
            $this->commandData->addDynamicVariable('$SIZE$', $minMaxRules);

            $fieldTemplate = HTMLFieldGenerator::generateHTML($field, $this->templateType);
            if (!empty($fieldTemplate)) {
                $fkSourceLang = "";
                if ($field->isForeignKey) {
                    $vars = $this->commandData->generateFKVars($field);
                    $fieldTemplate = fill_template([
                        '$FIELD_NAME_CAMEL$' => $vars['$SOURCE_TABLE_NAME_SINGULAR_CAMEL$'],
                        '$MODEL_NAME_CAMEL$' => $vars['$SOURCE_TABLE_NAME_SINGULAR_CAMEL$'],
                        '$LANG_FIELD_NAME$' => $vars['$SOURCE_SELECTED_COLUMN$'],
                        '$LANG_FIELD_NAME$' => $vars['$SOURCE_SELECTED_COLUMN$'],
                        '$FK_SOURCE$' => "__('models/{$vars['$SOURCE_TABLE_NAME_SINGULAR_CAMEL$']}.singular') . \" \" . ",
                    ], $fieldTemplate);
                }
                $fieldTemplate = fill_template([
                    '$FK_SOURCE$' => $fkSourceLang
                ], $fieldTemplate);

                $fieldTemplate = fill_template_with_field_data(
                    $this->commandData->dynamicVars,
                    $this->commandData->fieldNamesMapping,
                    $fieldTemplate,
                    $field
                );
                $this->htmlFields[] = $fieldTemplate;
            }
        }

        $templateData = get_template('views.'.$templateName, $this->templateType);
        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        $templateData = str_replace('$FIELDS$', implode("\n\n", $this->htmlFields), $templateData);

        FileUtil::createFile($this->path, 'fields.blade.php', $templateData);
        $this->commandData->commandInfo('field.blade.php created');
    }

    private function generateViewComposer($tableName, $variableName, $columns, $selectTable, $modelName = null)
    {
        $templateName = 'fields.select';
        $fieldTemplate = get_template($templateName, $this->templateType);

        $viewServiceProvider = new ViewServiceProviderGenerator($this->commandData);
        $viewServiceProvider->generate();
        $viewServiceProvider->addViewVariables($tableName.'.fields', $variableName, $columns, $selectTable, $modelName);

        $fieldTemplate = str_replace(
            '$INPUT_ARR$',
            '$'.$variableName,
            $fieldTemplate
        );

        return $fieldTemplate;
    }

    private function generateCreate()
    {
        $templateName = 'create_modal';

        $templateData = get_template('views.'.$templateName, $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'create_modal.blade.php', $templateData);
        $this->commandData->commandInfo('create_modal.blade.php created');
    }

    private function generateUpdate()
    {
        $templateName = 'edit_modal';

        $templateData = get_template('views.'.$templateName, $this->templateType);

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);

        FileUtil::createFile($this->path, 'edit_modal.blade.php', $templateData);
        $this->commandData->commandInfo('edit_modal.blade.php created');
    }

    public function rollback()
    {
        deleteDir($this->path);
    }
}
