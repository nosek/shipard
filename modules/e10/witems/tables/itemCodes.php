<?php

namespace e10\witems;
use \Shipard\Form\TableForm, \Shipard\Table\DbTable;


/**
 * Class TableItemCodes
 */
class TableItemCodes extends DbTable
{
	public function __construct ($dbmodel)
	{
		parent::__construct ($dbmodel);
		$this->setName ('e10.witems.itemCodes', 'e10_witems_itemCodes', 'Kódy položek');
	}

  public function checkBeforeSave (&$recData, $ownerData = NULL)
	{
		parent::checkBeforeSave ($recData, $ownerData);

    $codeKind = $this->app()->cfgItem('e10.witems.codesKinds.'.$recData['codeKind']);
    $refType = $codeKind['refType'] ?? 0;
    $askDir = $codeKind['askDir'] ?? 0;
    $askPerson = $codeKind['askPerson'] ?? 0;
    $askPersonsGroup = $codeKind['askPersonsGroup'] ?? 0;

    if ($refType == 1)
    {
      $ni = $this->db()->query('SELECT * FROM [e10_base_nomencItems] WHERE [ndx] = %i', $recData['itemCodeNomenc'])->fetch();
      if ($ni)
      {
        $recData['itemCodeText'] = $ni['itemId'];
      }
    }
    else
      $recData['itemCodeNomenc'] = 0;

    if (!$askPerson)
      $recData['person'] = 0;
    if (!$askDir)
      $recData['codeDir'] = 0;
    if (!$askPersonsGroup)
      $recData['personsGroup'] = 0;

    $recData ['systemOrder'] = 99;

    if ($recData['codeDir'])
      $recData ['systemOrder']--;

    if ($recData['person'])
      $recData ['systemOrder']--;

    if ($recData['personsGroup'] != 0)
      $recData ['systemOrder']--;
  }
}


/**
 * Class FormItemCode
 */
class FormItemCode extends TableForm
{
	public function renderForm ()
	{
		$this->setFlag ('formStyle', 'e10-formStyleSimple');

    $codeKind = $this->app()->cfgItem('e10.witems.codesKinds.'.$this->recData['codeKind']);
    $refType = $codeKind['refType'] ?? 0;
    $askDir = $codeKind['askDir'] ?? 0;
    $askPerson = $codeKind['askPerson'] ?? 0;
    $askPersonsGroup = $codeKind['askPersonsGroup'] ?? 1;

		$this->openForm (TableForm::ltGrid);
			$this->openRow();
				$this->addColumnInput ('codeKind', self::coColW4);
				//$this->addColumnInput ('itemCodeText', self::coColW8);
        //$this->addInputIntRef ('itemCodeRef', 'e10.base.nomencItems', 'Test', self::coColW8);
        if ($refType === 1)
          $this->addColumnInput ('itemCodeNomenc', self::coColW8);
        else
          $this->addColumnInput ('itemCodeText', self::coColW8);
			$this->closeRow();

      if ($askDir || $askPerson || $askPersonsGroup)
      {
        if ($askDir && $askPerson && $askPersonsGroup)
        {
          $this->addColumnInput ('codeDir', self::coColW2);
          $this->addColumnInput ('personsGroup', self::coColW5);
          $this->addColumnInput ('person', self::coColW5);
        }
        elseif ($askDir && $askPerson)
        {
          $this->addColumnInput ('codeDir', self::coColW2);
          $this->addColumnInput ('person', self::coColW10);
        }
        elseif ($askDir && !$askPerson)
        {
          $this->addColumnInput ('codeDir', self::coColW4);
        }
        elseif (!$askDir && $askPerson)
        {
          $this->addColumnInput ('person', self::coColW12);
        }
      }
		$this->closeForm ();
	}
}
