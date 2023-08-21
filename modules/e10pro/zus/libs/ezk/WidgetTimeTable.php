<?php

namespace e10pro\zus\libs\ezk;
require_once __SHPD_MODULES_DIR__ . 'e10pro/zus/zus.php';
use \Shipard\Utils\Utils, \Shipard\Utils\Str;


/**
 * Class WidgetTimeTable
 */
class WidgetTimeTable extends \Shipard\UI\Core\WidgetPane
{
	var $teacher;
	var $today;
	var $planMode = 'week';

	/** @var \e10\table */
	var $tableHodiny;

	var $firstDay;
	var $lastDay;

	var $academicYear;
	var $dataTimeTable = [];

	var $studentNdx = 0;
	var $userContext = NULL;


	function loadData()
	{
		$this->loadTimeTable();
	}

	function loadTimetable ()
	{
		$q[] = 'SELECT rozvrh.*, pobocky.shortName as pobockaId, vyuky.nazev as vyukaNazev, vyuky.typ as typVyuky, vyuky.rocnik as rocnik, predmety.nazev as predmetNazev, ucebny.shortName as ucebnaNazev, ucitele.fullName AS ucitelJmeno';
		array_push ($q, ' FROM [e10pro_zus_vyukyrozvrh] AS rozvrh');
		array_push ($q, ' LEFT JOIN e10_base_places AS pobocky ON rozvrh.pobocka = pobocky.ndx');
		array_push ($q, ' LEFT JOIN e10_base_places AS ucebny ON rozvrh.ucebna = ucebny.ndx');
		array_push ($q, ' LEFT JOIN e10pro_zus_vyuky AS vyuky ON rozvrh.vyuka = vyuky.ndx');
		array_push ($q, ' LEFT JOIN e10pro_zus_predmety AS predmety ON rozvrh.predmet = predmety.ndx');
		array_push ($q, ' LEFT JOIN e10_persons_persons AS ucitele ON rozvrh.ucitel = ucitele.ndx');
		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND vyuky.skolniRok = %s', $this->academicYear);
		array_push ($q, ' AND rozvrh.stavHlavni <= 2');
		array_push ($q, ' AND rozvrh.vyuka IN %in', $this->userContext['vyuky']);
		array_push ($q, ' ORDER BY rozvrh.den, rozvrh.zacatek, rozvrh.ndx');

		$rows = $this->db()->query ($q);
		foreach ($rows as $r)
		{
			$item = [
				'ndx' => $r['ndx'], 'pobocka' => $r['pobocka'], 'pobockaId' => $r['pobockaId'],
				'ucebna' => $r['ucebna'], 'ucebnaNazev' => $r['ucebnaNazev'],
				'zacatek' => $r['zacatek'], 'konec' => $r['konec'],
				'vyuka' => $r['vyuka'], 'vyukaNazev' => $r['vyukaNazev'], 'typVyuky' => $r['typVyuky'], 'rozvrh' => $r['ndx'],
				'predmetNazev' => $r['predmetNazev'],
				'ucitelJmeno' => $r['ucitelJmeno']
				//'rocnik' => zusutils::rocnikVRozvrhu($this->app, $r['rocnik'], $r['typVyuky'], 'zkratka'),
			];
			$this->dataTimeTable[$r['den']][] = $item;
		}
	}

	function renderData()
	{
		$table = [];
		foreach ($this->dataTimeTable as $dayId => $dayContent)
		{
			$item = [
				'time' => Utils::$dayNames[$dayId],

			];

			$table[] = $item;

			foreach ($dayContent as $hour)
			{
				$info = [];
				$info[] = ['text' => $hour['predmetNazev'], 'class' => 'h5 clearfix'];
				if ($hour['typVyuky'] == 0)
					$info[] = ['text' => $hour['vyukaNazev'], 'class' => 'clearfix'];
				$info[] = ['text' => $hour['ucebnaNazev'], 'class' => 'clearfix'];
				$info[] = ['text' => $hour['ucitelJmeno'], 'class' => 'clearfix'];

				$item = [
					'time' => $hour['zacatek'].' - '.$hour['konec'],
					'info' => $info,
				];

				$table[] = $item;
			}
		}
		$h = [
			'time' => 'Čas',
			'info' => 'Předmět',
		];

		$this->addContent (['type' => 'table', 'table' => $table, 'header' => $h]);
	}

	public function createContent ()
	{
		$this->today = utils::today();
		$this->academicYear = '2022';//zusutils::aktualniSkolniRok();

		$userContexts = $this->app()->uiUserContext ();
		$ac = $userContexts['contexts'][$this->app()->uiUserContextId] ?? NULL;
		if ($ac)
			$this->studentNdx = $ac['studentNdx'] ?? 0;

		$this->userContext = $userContexts['ezk']['students'][$this->studentNdx];

		$this->loadData();
		$this->renderData();
	}

	public function title()
	{
		return FALSE;
	}
}
