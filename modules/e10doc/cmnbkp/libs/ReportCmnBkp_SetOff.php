<?php

namespace e10doc\cmnbkp\libs;


class ReportCmnBkp_SetOff extends \e10doc\core\libs\reports\DocReport
{
	function init ()
	{
		parent::init();

		$this->setReportId('reports.default.e10doc.cmnbkp.set-off');
	}
}
