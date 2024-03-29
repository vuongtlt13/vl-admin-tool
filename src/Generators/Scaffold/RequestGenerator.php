<?php

namespace Vuongdq\VLAdminTool\Generators\Scaffold;

use Vuongdq\VLAdminTool\Common\CommandData;
use Vuongdq\VLAdminTool\Generators\BaseGenerator;
use Vuongdq\VLAdminTool\Generators\ModelGenerator;
use Vuongdq\VLAdminTool\Utils\FileUtil;

class RequestGenerator extends BaseGenerator
{
    /** @var CommandData */
    private $commandData;

    /** @var string */
    private $path;

    /** @var string */
    private $createFileName;

    /** @var string */
    private $updateFileName;

    public function __construct(CommandData $commandData)
    {
        $this->commandData = $commandData;
        $this->path = $commandData->config->pathRequest;
        $this->createFileName = 'Create'.$this->commandData->modelName.'Request.php';
        $this->updateFileName = 'Update'.$this->commandData->modelName.'Request.php';
    }

    public function generate()
    {
        $this->generateCreateRequest();
        $this->generateUpdateRequest();
    }

    private function generateCreateRequest()
    {
        $modelGenerator = new ModelGenerator($this->commandData);
        $templateData = get_template('scaffold.request.create_request', 'vl-admin-tool');

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);
        $templateData = str_replace('$RULES$', implode(','.infy_nl_tab(1, 3), $modelGenerator->generateRules()), $templateData);

        FileUtil::createFile($this->path, $this->createFileName, $templateData);

        $this->commandData->commandComment("\nCreate Request created: ");
        $this->commandData->commandInfo($this->createFileName);
    }

    private function generateUpdateRequest()
    {
        $modelGenerator = new ModelGenerator($this->commandData);

        $templateData = get_template('scaffold.request.update_request', 'vl-admin-tool');

        $templateData = fill_template($this->commandData->dynamicVars, $templateData);
        $templateData = str_replace('$RULES$', implode(','.infy_nl_tab(1, 3), $modelGenerator->generateRules('update')), $templateData);

        FileUtil::createFile($this->path, $this->updateFileName, $templateData);

        $this->commandData->commandComment("\nUpdate Request created: ");
        $this->commandData->commandInfo($this->updateFileName);
    }

    public function rollback()
    {
        if ($this->rollbackFile($this->path, $this->createFileName)) {
            $this->commandData->commandComment('Create API Request file deleted: '.$this->createFileName);
        }

        if ($this->rollbackFile($this->path, $this->updateFileName)) {
            $this->commandData->commandComment('Update API Request file deleted: '.$this->updateFileName);
        }
    }
}
