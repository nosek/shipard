<?php

namespace e10pro\zus;
use \e10\utils;
use \e10\base\libs\UtilsBase;

/**
 * Class ModuleServices
 * @package e10pro\zus
 */
class ModuleServices extends \E10\CLI\ModuleServices
{
	public function onAppUpgrade ()
	{
		$s [] = ['end' => '2020-12-31', 'sql' => "UPDATE e10pro_zus_hodiny SET stavHlavni = 3 WHERE stav = 4000 AND stavHlavni = 2"];

		$s [] = ['end' => '2021-02-28', 'sql' => "DELETE FROM e10pro_zus_vyukystudenti WHERE studium = 0 OR studium IS NULL"];
		$s [] = ['end' => '2021-02-28', 'sql' => "DELETE FROM e10pro_zus_hodinydochazka WHERE student = 0 OR student IS NULL"];

		$this->doSqlScripts ($s);

		//$this->upgradeSkupinoveDochazkyBezStudentu();
	}

	public function anonymizeVyuky ()
	{
		$q [] = 'SELECT vyuky.*, studenti.fullName as jmenoStudenta';
		array_push($q, ' FROM [e10pro_zus_vyuky] as vyuky ');
		array_push($q, ' LEFT JOIN e10_persons_persons AS studenti ON vyuky.student = studenti.ndx');
		array_push($q, ' LEFT JOIN e10pro_zus_studium AS studium ON vyuky.studium = studium.ndx');

		$rows = $this->app->db()->query ($q);
		foreach ($rows as $r)
		{
			if ($r['typ'] == 1)
				$this->app->db()->query ('UPDATE [e10pro_zus_vyuky] SET nazev = %s', $r['jmenoStudenta'], ' WHERE ndx = ', $r['ndx']);
			else
				$this->app->db()->query ('UPDATE [e10pro_zus_vyuky] SET nazev = %s', mt_rand(1, 4).'. skupina '.strtoupper($this->app->faker->randomLetter()), ' WHERE ndx = ', $r['ndx']);
		}
	}

	public function anonymizeStudia ()
	{
		$q [] = 'SELECT studium.*, ucitel.fullName as ucitelFullName, student.fullName as studentFullName, student.lastName as studentLastName, student.company as studentCompany, student.gender as studentGender, places.fullName as placeName';
		$q [] = ' FROM [e10pro_zus_studium] as studium ';
		$q [] = ' LEFT JOIN e10_persons_persons AS ucitel ON studium.ucitel = ucitel.ndx ';
		$q [] = ' LEFT JOIN e10_persons_persons AS student ON studium.student = student.ndx ';
		$q [] = ' LEFT JOIN e10_base_places AS places ON studium.misto = places.ndx ';
		$q [] = ' WHERE 1';

		$rows = $this->app->db()->query ($q);
		foreach ($rows as $r)
		{
			if ($r['student'] != 0)
			{
				$nazev = $r ['studentFullName'];
				$nazev .= ' ('.$r['cisloStudia'].')';
				$nazev .= ' / '.$this->app->cfgItem ("e10pro.zus.oddeleni.{$r ['svpOddeleni']}.nazev");
				$nazev .= ' / '.$this->app->cfgItem ("e10pro.zus.roky.{$r ['skolniRok']}.nazev");
				$this->app->db()->query ('UPDATE [e10pro_zus_studium] SET nazev = %s', $nazev, ' WHERE ndx = ', $r['ndx']);
			}
		}
	}

	public function anonymizePobocky ()
	{
		$q [] = 'SELECT places.* FROM [e10_base_places] AS places';
		array_push ($q, ' WHERE places.[placeType] = %s', 'lcloffc');

		$cities = [];

		$rows = $this->app->db()->query ($q);
		foreach ($rows as $r)
		{
			while (1)
			{
				$city = $this->app->faker->city;
				if (!in_array($city, $cities))
					break;
			}
			$cities[] = $city;

			$street = $this->app->faker->streetName;
			$fullName = $city.', '.$street;

			$this->app->db()->query ('UPDATE [e10_base_places] SET fullName = %s', $fullName, ', shortName = %s', $city, ' WHERE ndx = ', $r['ndx']);
		}
	}

	public function onAnonymize ()
	{
		$this->anonymizePobocky();
		$this->anonymizeStudia();
		$this->anonymizeVyuky();
	}

	protected function upgradeSkupinoveDochazkyBezStudentu()
	{
		$qd = [];
		array_push ($qd, 'SELECT dochazka.*, studia.student AS studiumStudent');
		array_push ($qd, ' FROM e10pro_zus_hodinydochazka AS dochazka');
		array_push ($qd, ' LEFT JOIN e10pro_zus_studium AS studia ON dochazka.studium = studia.ndx');
		array_push ($qd, ' WHERE dochazka.student = %i', 0);
		array_push ($qd, ' ORDER BY dochazka.ndx');
		$rowsDochazka = $this->db()->query($qd);
		foreach ($rowsDochazka as $rd)
		{
			if (!$rd['studiumStudent'])
			{
				echo "ERROR: chybne/neexistujici studium v kolektivni dochazce\n";
				continue;
			}
			$this->db()->query ('UPDATE e10pro_zus_hodinydochazka SET student = %i', $rd['studiumStudent'], ' WHERE [ndx] = %i', $rd['ndx']);
		}
	}

	protected function upgradeSkupinoveDochazky()
	{
		$this->upgradeSkupinoveDochazky_OnePart(1);
		$this->upgradeSkupinoveDochazky_OnePart(0);
	}

	protected function upgradeSkupinoveDochazky_OnePart($singleRows)
	{
		$q = [];
		array_push ($q, ' SELECT vyuky.nazev AS nazevVyuky, vyuky.skolniRok AS skolniRokVyuky, vyuky.ndx AS vyukaNdx, dochazka.hodina AS hodinaNdx,');
		array_push ($q, ' dochazka.student AS studentNdx, studenti.fullName as jmenoStudenta, COUNT(dochazka.student) AS cnt,');
		array_push ($q, ' hodiny.datum AS hodinaDatum');
		array_push ($q, ' FROM e10pro_zus_hodinydochazka AS dochazka');
		array_push ($q, ' LEFT JOIN e10pro_zus_hodiny AS hodiny ON dochazka.hodina = hodiny.ndx');
		array_push ($q, ' LEFT JOIN e10pro_zus_vyuky AS vyuky ON hodiny.vyuka = vyuky.ndx');
		array_push ($q, ' LEFT JOIN e10_persons_persons AS studenti ON dochazka.student = studenti.ndx');
		array_push ($q, ' WHERE 1');
		array_push ($q, ' AND (dochazka.studium = 0 OR dochazka.studium IS NULL)');
		//array_push ($q, ' AND vyuky.skolniRok = %s', '2020');
		array_push ($q, ' GROUP BY hodina, dochazka.student');

		if ($singleRows)
			array_push ($q, ' HAVING cnt = 1');
		else
			array_push ($q, ' HAVING cnt > 1');
		//array_push ($q, ' limit %i', 2500);

		$cnt = 0;
		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			//echo $r['nazevVyuky'] . " ". $r['skolniRokVyuky'].": " . $r['jmenoStudenta'] . "\n";
			if ($cnt % 1000 === 0)
				echo sprintf("%06d ", $cnt);
			if ($cnt && $cnt % 10000 === 0)
				echo "\n";

			// -- nacteni studii
			// studia z vyuky
			$studiaStudenta = [];
			$rowsStudia = $this->db()->query('SELECT vs.* FROM e10pro_zus_vyukystudenti AS vs ',
				' LEFT JOIN e10pro_zus_studium AS studia ON vs.studium = studia.ndx',
				' WHERE studia.student = %i', $r['studentNdx'], ' AND vs.vyuka = %i', $r['vyukaNdx'], ' ORDER BY vs.ndx');
			foreach ($rowsStudia as $rs)
				$studiaStudenta[] = $rs['studium'];
			// stara studia z archivu
			$rowsStudia = $this->db()->query('SELECT studia.* FROM e10pro_zus_studium AS studia ',
				' WHERE studia.student = %i', $r['studentNdx'], ' AND studia.skolniRok = %s', $r['skolniRokVyuky'], ' ORDER BY studia.stavHlavni, studia.ndx');
			foreach ($rowsStudia as $rs)
				if (!in_array($rs['ndx'], $studiaStudenta))
					$studiaStudenta[] = $rs['ndx'];


			//echo \dibi::$sql."\n";
			//echo " --> studia: ".json_encode($studiaStudenta)."";


			// -- update hodin
			$qd = [];
			array_push ($qd, 'SELECT * ');
			array_push ($qd, ' FROM e10pro_zus_hodinydochazka');
			array_push ($qd, ' WHERE student = %i', $r['studentNdx']);
			array_push ($qd, ' AND hodina = %i', $r['hodinaNdx']);
			array_push ($qd, ' ORDER BY ndx');
			$rowsDochazka = $this->db()->query($qd);
			$stc = 0;
			foreach ($rowsDochazka as $rd)
			{
				if (!isset($studiaStudenta[$stc]))
				{
					echo "\n--> ERROR ON ROW {$stc}: hodina ".json_encode($rd->toArray())."\n";
					echo " --> studia: ".json_encode($studiaStudenta)."\n";
					echo " -->" . $r['nazevVyuky'] . " ". $r['skolniRokVyuky'].": " . $r['jmenoStudenta']." - ".utils::datef($r['hodinaDatum']);
					echo "\n";
					continue;
				}
				//echo "   --> hodina: ".json_encode($rd->toArray())."\n";
				$this->db()->query ('UPDATE e10pro_zus_hodinydochazka SET studium = %i', $studiaStudenta[$stc], ' WHERE [ndx] = %i', $rd['ndx']);
				$stc++;
				//echo ".";
			}

			//echo "\n";

			$cnt++;
		}


		echo "\n------TOTAL: $cnt \n\n";
	}

	protected function repairFees()
	{
		$yearParam = $this->app()->arg('year');
		if (!$yearParam)
		{
			echo "Missing `--year` param!\n";
			return FALSE;
		}

		$halfParam = intval($this->app()->arg('half'));
		if ($halfParam !== 1 && $halfParam !== 2)
		{
			echo "Missing / bad `--half` param!\n";
			return FALSE;
		}

		$addFeeParam = intval($this->app()->arg('addFee'));

		$e = new \e10pro\zus\libs\RepairFeesEngine($this->app());
		$e->schoolYear = $yearParam;
		$e->half = $halfParam;
		$e->addFee = $addFeeParam;
		$e->run();

		return TRUE;
	}

	protected function repairInvoices()
	{
		$yearParam = $this->app()->arg('year');
		if (!$yearParam)
		{
			echo "Missing `--year` param!\n";
			return FALSE;
		}

		$halfParam = intval($this->app()->arg('half'));
		if ($halfParam !== 1 && $halfParam !== 2)
		{
			echo "Missing / bad `--half` param!\n";
			return FALSE;
		}

		$e = new \e10pro\zus\libs\RepairInvoicesEngine($this->app());
		$e->schoolYear = $yearParam;
		$e->half = $halfParam;
		$e->run();

		return TRUE;
	}

	public function importContacts()
	{
		$tableContacts = $this->app()->table ('e10.persons.personsContacts');

		$q = [];
		array_push ($q, 'SELECT * FROM e10_persons_persons');
		array_push ($q, ' WHERE 1');
		//array_push ($q, ' AND [ndx] = %i', 2126);
		array_push ($q, ' ORDER BY [ndx]');

		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$personNdx = $r['ndx'];
			echo "* ".$r['fullName']."\n";

			$properties = UtilsBase::getPropertiesTable ($this->app(), 'e10.persons.persons', $personNdx);

			if (isset($properties['e10-zus-zz-1']))
			{
				$this->importContactsAdd($tableContacts,
					$properties['e10-zus-zz-1']['e10-zus-zz-jmeno'][0]['value'] ?? '',
					$properties['e10-zus-zz-1']['e10-zus-zz-email'][0]['value'] ?? '',
					$properties['e10-zus-zz-1']['e10-zus-zz-telefon'][0]['value'] ?? '',
					'zz1',
					$personNdx
				);
			}

			if (isset($properties['e10-zus-zz-2']))
			{
				$this->importContactsAdd($tableContacts,
					$properties['e10-zus-zz-2']['e10-zus-zz2-jmeno'][0]['value'] ?? '',
					$properties['e10-zus-zz-2']['e10-zus-zz2-email'][0]['value'] ?? '',
					$properties['e10-zus-zz-2']['e10-zus-zz2-telefon'][0]['value'] ?? '',
					'zz2',
					$personNdx
				);
			}

			$this->db()->query('DELETE FROM [e10_base_properties] WHERE [tableid] = %s', 'e10.persons.persons',
												 ' AND [group] IN %in', ['e10-zus-zz-1', 'e10-zus-zz-2'],
												 ' AND [recid] = %i', $personNdx);

			//print_r($properties);
		}
	}

	protected function importContactsAdd(\e10\persons\TablePersonsContacts $tableContacts, $name, $email, $phone, $role, $personNdx)
	{
		$newAddress = [
			'person' => $personNdx,

			'flagAddress' => 0,
			'flagMainAddress' => 0,
			'flagOffice' => 0,
			'onTop' => 0,
			'flagContact' => 1,

			'contactName' => $name,
			'contactEmail' => $email,
			'contactPhone' => $phone,

			'contactRole' => $role,

			'docState' => 4000,
			'docStateMain' => 2,
		];

		$tableContacts->dbInsertRec($newAddress);

		if ($name !== '')
		{
			$this->db()->query('DELETE FROM [e10_base_properties] WHERE [tableid] = %s', 'e10.persons.persons',
				' AND [group] = %s', 'contacts', ' AND [property] = %s', 'email',
				' AND [valueString] = %s', $email,
				' AND [recid] = %i', $personNdx);
			$this->db()->query('DELETE FROM [e10_base_properties] WHERE [tableid] = %s', 'e10.persons.persons',
				' AND [group] = %s', 'contacts', ' AND [property] = %s', 'phone',
				' AND [valueString] = %s', $phone,
				' AND [recid] = %i', $personNdx);
		}
	}

	public function repairEntries()
	{
		$q = [];
		array_push($q, 'SELECT * FROM e10pro_zus_prihlasky');
		array_push($q, ' WHERE 1');

		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$pid = $r ['rodneCislo'];
			if (strlen($pid) === 10)
			{
				echo $r['fullNameS'].': '.$pid;
				$pid = substr($r ['rodneCislo'], 0, 6).'/'.substr($r['rodneCislo'], 6);
				echo ' --> '.$pid."\n";
				$this->db()->query('UPDATE e10pro_zus_prihlasky SET rodneCislo = %s', $pid, ' WHERE ndx = %i', $r['ndx']);
			}
		}
	}

	public function repairPIDs()
	{
		$q = [];
		array_push($q, 'SELECT props.*, persons.fullName AS fullNameS');
		array_push($q, ' FROM e10_base_properties AS props');
		array_push($q, ' LEFT JOIN e10_persons_persons AS persons ON props.recid = persons.ndx');
		array_push($q, ' WHERE 1');
		array_push($q, ' AND props.[group] = %s', 'ids');
		array_push($q, ' AND props.[property] = %s', 'pid');
		array_push($q, ' AND props.[tableid] = %s', 'e10.persons.persons');

		$rows = $this->db()->query($q);
		foreach ($rows as $r)
		{
			$pid = $r ['valueString'];
			if (strlen($pid) === 10 && !strchr($r ['valueString'], '/'))
			{
				echo $r['fullNameS'].': '.$pid;
				$pid = substr($r ['valueString'], 0, 6).'/'.substr($r['valueString'], 6);
				echo ' --> '.$pid./*' -> '.json_encode($r->toArray()).*/"\n";
				$this->db()->query('UPDATE e10_base_properties SET valueString = %s', $pid, ' WHERE ndx = %i', $r['ndx']);
			}
		}
	}

	public function onCliAction ($actionId)
	{
		switch ($actionId)
		{
			case 'upgrade-skupinove-dochazky': return $this->upgradeSkupinoveDochazky();
			case 'send-entries-emails': return $this->sendEntriesEmails();
			case 'repair-fees': return $this->repairFees();
			case 'repair-invoices': return $this->repairInvoices();
			case 'import-contacts': return $this->importContacts();
			case 'repair-entries': return $this->repairEntries();
			case 'repair-pids': return $this->repairPIDs();
		}

		parent::onCliAction($actionId);
	}

	public function sendEntriesEmails()
	{
		$e = new \e10pro\zus\libs\SendEntriesEmails($this->app());
		$e->sendAll();
	}

	public function onCronEver ()
	{
		$this->sendEntriesEmails();
	}

	public function onCron ($cronType)
	{
		switch ($cronType)
		{
			case 'ever': $this->onCronEver(); break;
		}
		return TRUE;
	}

}
