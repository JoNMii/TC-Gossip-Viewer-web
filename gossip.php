<html><head><link rel="stylesheet" src="gossip.css" />
<script type="text/javascript" src="gossip.js"></script></head><body><?php
	$type = 0;
	$entry = 0;
	
	if (isset($_GET['entry']))
		$entry = max(0,(int)$_GET['entry']);
	if (isset($_GET['type']))
		$type = max(0,(int)$_GET['type']);
	if ($entry && $type)
	{
		$db = new PDO('mysql:host=127.0.0.1;dbname=world', 'trinity', 'trinity');
		
		function text($text)
		{
			return str_replace('$B$B',"\n",$text);
		}
		function recursiveGossipParse($gossipMenuID)
		{
			global $db, $msg;
			
			$menu = new stdClass();
			
			static $queryGM = null;
			if (!$queryGM)
				$queryGM = $db->prepare('SELECT text_id, VerifiedBuild FROM gossip_menu WHERE entry = :gossipMenuID');
			$queryGM->bindValue(':gossipMenuID', $gossipMenuID, PDO::PARAM_INT);
			if (!$queryGM->execute() || !$queryGM->rowCount())
			{
				$msg = 'queryGM failed for '.$gossipMenuID;
				return false;
			}
			$resultGM = $queryGM->fetchObject();
			$menu->id = $gossipMenuID;
			$menu->textId = +$resultGM->text_id;
			$menu->verifiedBuild = +$resultGM->VerifiedBuild;
			
			static $queryNT = null;
			if (!$queryNT)
				$queryNT = $db->prepare('SELECT
											if(nt.BroadcastTextID0 = 0, nt.text0_0, (SELECT bt.MaleText FROM broadcast_text bt WHERE bt.ID = nt.BroadcastTextID0 AND bt.Language=0)) as MaleText,
											if(nt.BroadcastTextID0 = 0, nt.text0_1, (SELECT bt.FemaleText FROM broadcast_text bt WHERE bt.ID = nt.BroadcastTextID0 AND bt.Language=0)) as FemaleText
										FROM npc_text nt WHERE nt.ID = :npcTextID');
			$queryNT->bindValue(':npcTextID',$menu->textId);
			if (!$queryNT->execute() || !$queryNT->rowCount())
			{
				$msg = 'queryNT failed for '.$gossipMenuID.', NTI '.$menu->textId;
				return false;
			}
			$resultNT = $queryNT->fetchObject();
			$menu->maleText = text($resultNT->MaleText);
			$menu->femaleText = text($resultNT->FemaleText);
			
			static $queryGMO = null;
			if (!$queryGMO)
				$queryGMO = $db->prepare('SELECT gmo.id, gmo.option_icon, IF(gmo.OptionBroadcastTextID=0, gmo.option_text, (SELECT bt.MaleText FROM broadcast_text bt WHERE bt.ID = gmo.OptionBroadcastTextID AND bt.language = 0)) as option_text, gmo.action_menu_id, gmo.option_id FROM gossip_menu_option gmo WHERE gmo.menu_id = :gossipMenuID');
			$queryGMO->bindValue(':gossipMenuID', $gossipMenuID, PDO::PARAM_INT);
			if (!$queryGMO->execute())
			{
				$msg = 'queryGMO failed for '.$gossipMenuID;
				return false;
			}
			if ($queryGMO->rowCount())
			{
				$menu->options = array();
				while ($option = $queryGMO->fetchObject())
				{
					$optionData = new stdClass();
					$optionData->id = +$option->id;
					$optionData->icon = +$option->option_icon;
					$optionData->text = text($option->option_text);
					$optionData->action = +$option->option_id;
					$optionData->menuID = +$option->action_menu_id;
					$menu->options[] = $optionData;
				}
				for ($i=0; $i < count($menu->options); ++$i)
					if ($menu->options[$i]->menuID)
						if (!$menu->options[$i]->childMenu = recursiveGossipParse($menu->options[$i]->menuID))
							return false;
			}

			return $menu;
		}
		
		function recursiveGossipPrint($heading,$menu)
		{
			echo '<div class="gossipmenu">';
				echo '<div class="heading-container"><div class="heading">'.htmlentities($heading,ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED|ENT_HTML5,'UTF-8').'</div></div>';
				echo '<div class="menuid">'.$menu->id.'</div>';
				echo '<div class="maletext"><div class="textid">'.$menu->textId.'</div>'.htmlentities($menu->maleText,ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED|ENT_HTML5,'UTF-8').'</div>';
				echo '<div class="femaletext"><div class="textid">'.$menu->textId.'</div>'.htmlentities($menu->femaleText,ENT_QUOTES|ENT_SUBSTITUTE|ENT_DISALLOWED|ENT_HTML5,'UTF-8').'</div>';
				echo '<div class="build">'.$menu->verifiedBuild.'</div>';
				if (isset($menu->options))
				{
					echo '<div class="children">';
					foreach ($menu->options as $option)
					{
						if ($option->menuID)
							recursiveGossipPrint('['.$option->id.', '.$option->action.'] '.$option->text, $option->childMenu);
						else
							echo '<div class="gossipmenu"><div class="emptychild-container"><div class="emptychild">'.'['.$option->id.', '.$option->action.'] '.$option->text.'</div></div></div>';
					}
					echo '</div>';
				}
			echo '</div>';
		}
		
		echo '<div id="genderbox"></div><div id="gender"><input id="gender-male" type="radio" name="gender" value="0" /> <label for="gender-male">Male</label> <input id="gender-female" type="radio" name="gender" value="1" /> <label for="gender-female">Female</label></div>';
		$startFemale = false;
		$gossipHeading = '';
		$gossipMenuID = 0;
		if ($type == 2)
			$gossipMenuID = $entry;
		else
		{
			$queryCT = $db->prepare('SELECT ct.name, ct.gossip_menu_id, cmi.gender FROM creature_template ct LEFT JOIN creature_model_info cmi ON ct.modelid1=cmi.displayid WHERE ct.entry = :entry');
			$queryCT->bindValue(':entry',$entry,PDO::PARAM_INT);
			if ($queryCT->execute())
				if ($queryCT->rowCount())
				{
					$resultCT = $queryCT->fetchObject();
					$startFemale = ($resultCT->gender === '1');
					$gossipHeading = $resultCT->name;
					$gossipMenuID = +$resultCT->gossip_menu_id;
				}
		}
		
		if (!$gossipMenuID)
		{
			$msg = 'No gossip ID for creature or no valid gossip specified.';
			goto render;
		}
		
		$mainGossipMenu = recursiveGossipParse($gossipMenuID);
		if (!$mainGossipMenu)
			goto render;
		recursiveGossipPrint($gossipHeading,$mainGossipMenu);
		
		render:
		if (isset($msg))
			echo '<pre>'.$msg.'</pre>';
		else
			echo '<script type="text/javascript">setupGender(); setFemale('.($startFemale?'true':'false').');</script>';
	}
	else
	{
	?>
	<form action="" method="GET">
		<div><input type="radio" name="type" value="1" checked="checked" /> creature_template.entry</div>
		<div><input type="radio" name="type" value="2" /> gossip_menu.entry</div>
		<div><input type="number" name="entry" /></div>
		<div><input type="submit" value="Plot" /></div>
	</form><?php
	}
?></body></html>
