<?php

namespace services\nomenc;


/**
 * Class ModuleServices
 * @package services\nomenc
 */
class ModuleServices extends \E10\CLI\ModuleServices
{
	public function cliImport()
	{
		$ip = new \services\nomenc\libs\NomeclatureImport($this->app);

		$nomencId = $this->app->arg('id');
		$ip->nomencId = $nomencId;
		$ip->debug = 1;
		$ip->run();

		return TRUE;
	}

	public function onCliAction ($actionId)
	{
		switch ($actionId)
		{
			case 'import': return $this->cliImport();
		}

		parent::onCliAction($actionId);
	}
}
