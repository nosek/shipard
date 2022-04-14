<?php

namespace e10doc\proformasOut\libs;


/**
 * class ReportProformaOut
 */
class ReportProformaOut extends \e10doc\core\libs\reports\DocReport
{
	function init ()
	{
		parent::init();

		$this->setReportId('reports.default.e10doc.proformaOut.invpo');
	}

	public function loadData ()
	{
		parent::loadData();

		$spayd = new \e10doc\core\ShortPaymentDescriptor($this->app);
		$spayd->setBankAccount ('CZ', $this->data ['myBankAccount']['bankAccount'], $this->data ['myBankAccount']['iban'], $this->data ['myBankAccount']['swift']);
		$spayd->setAmount ($this->recData ['toPay'], $this->recData ['currency']);
		$spayd->setPaymentSymbols ($this->recData ['symbol1'], $this->recData ['symbol2']);

		$spayd->createString();
		$spayd->createQRCode();

		$this->data ['spayd'] = $spayd;
	}
}
