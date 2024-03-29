<?php

namespace Vuongdq\VLAdminTool\Repositories;

use Vuongdq\VLAdminTool\Models\DBConfig;
use Vuongdq\VLAdminTool\Repositories\BaseRepository;

/**
 * Class DBConfigRepository
 * @package Vuongdq\VLAdminTool\Repositories
 * @version January 7, 2021, 3:19 am UTC
*/

class DBConfigRepository extends BaseRepository
{
    /**
     * Configure the Model
     **/
    public function model()
    {
        return DBConfig::class;
    }
}
