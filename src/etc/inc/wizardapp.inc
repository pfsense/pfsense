<?php
/*
 * wizardapp.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006 Bill Marquette - bill.marquette@gmail.com.
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$gamesplist = array();

/* Game Consoles and Game Clients */

$gamesplist['playstationconsoles'] = array();
	/* Playstation 3, Playstation 4 and PS Vita */
	$gamesplist['playstationconsoles'][] = array('PS-Network-TCP', 'tcp', '10040', '10060', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-Network-UDP', 'udp', '50000', '60000', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-Home-TCP-1', 'tcp', '3478', '3480', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-Home-TCP-2', 'tcp', '8080', '8080', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-TCP-1', 'tcp', '5223', '5223', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-TCP-2', 'tcp', '10070', '10080', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-UDP-1', 'udp', '3478', '3479', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-UDP-2', 'udp', '3658', '3658', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-UDP-3', 'udp', '10070', '10070', 'both');
	$gamesplist['playstationconsoles'][] = array('PS-RemotePlay', 'tcp', '9293', '9293', 'both');

$gamesplist['wiiconsoles'] = array();
	/* XBox Consoles */
	$gamesplist['wiiconsoles'][] = array('Wii-Consoles-TCP-1', 'tcp', '6667', '6667', 'both');
	$gamesplist['wiiconsoles'][] = array('Wii-Consoles-TCP-2', 'tcp', '12400', '12400', 'both');
	$gamesplist['wiiconsoles'][] = array('Wii-Consoles-TCP-3', 'tcp', '28910', '28910', 'both');
	$gamesplist['wiiconsoles'][] = array('Wii-Consoles-TCP-4', 'tcp', '29900', '29901', 'both');
	$gamesplist['wiiconsoles'][] = array('Wii-Consoles-TCP-5', 'tcp', '29920', '29920', 'both');

$gamesplist['xboxconsoles'] = array();
	/* XBox Consoles */
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-UDP-1', 'udp', '88', '88', 'both');
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-UDP-2', 'udp', '3074', '3074', 'both');
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-TCP-1', 'tcp', '3074', '3074', 'both');
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-TCP-2', 'tcp', '3659', '3659', 'both');
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-TCP-3', 'tcp', '500', '500', 'both');
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-TCP-4', 'tcp', '3544', '3544', 'both');
	$gamesplist['xboxconsoles'][] = array('xbox-Consoles-TCP-5', 'tcp', '4500', '4500', 'both');

$gamesplist['battlenet'] = array();
	/* Blizzard Publishing games */
	$gamesplist['battlenet'][] = array('Battle.NET-game1-tcp', 'tcp', '6112', '6119', 'both'); //diablo, diablo2, starcraft, warcraft 2, warcraft 3
	$gamesplist['battlenet'][] = array('Battle.NET-game1-udp', 'udp', '6112', '6119', 'both'); //diablo, diablo2, starcraft, warcraft 2
	$gamesplist['battlenet'][] = array('Battle.NET-diablo2', 'tcp', '4000', '4000', 'both'); //diablo2
	$gamesplist['battlenet'][] = array('Battle.NET-game2', 'tcp', '1119', '1119', 'both');  //diablo3, starcraft 2
	$gamesplist['battlenet'][] = array('Battle.NET-game3', 'tcp', '3724', '3724', 'both');  //starcraft2

$gamesplist['eaorigin'] = array();
	/* EA Origin Client */
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-1', 'tcp', '1024', '1124', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-2', 'tcp', '9960', '9969', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-3', 'tcp', '18000', '18000', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-4', 'tcp', '18120', '18120', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-5', 'tcp', '18060', '18060', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-6', 'tcp', '27900', '27900', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-7', 'tcp', '28910', '28910', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-TCP-8', 'tcp', '29900', '29900', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-UDP-1', 'udp', '1024', '1124', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-UDP-2', 'udp', '18000', '18000', 'both');
	$gamesplist['eaorigin'][] = array('EA-Origin-UDP-3', 'udp', '29900', '29900', 'both');

$gamesplist['steam'] = array();
	/* Steam Games */
	$gamesplist['steam'][] = array('Steam-game-udp', 'udp', '27000', '27030', 'both');  //america's army 3, cs:s, cs:go, HL2, COD: Black Ops, COD: Black Ops 2, Natural Selection 2
	$gamesplist['steam'][] = array('Steam-game-tcp', 'tcp', '27000', '27030', 'both');  //america's army 3, cs:s, cs:go, HL2, COD: Black Ops, COD: Black Ops 2, Natural Selection 2
	$gamesplist['steam'][] = array('Steam-hltv', 'udp', '27015', '27030', 'both');
	$gamesplist['steam'][] = array('Steam-1', 'udp', '4380', '4380', 'both');
	$gamesplist['steam'][] = array('Steam-2', 'udp', '1200', '1200', 'both');
	$gamesplist['steam'][] = array('Steam-voice', 'udp', '3478', '3480', 'both');

$gamesplist['gamesforwindowslive'] = array();
	/* Games for Windows Live */
	$gamesplist['gamesforwindowslive'][] = array('Games4WinLive-1', 'udp', '88', '88', 'both');
	$gamesplist['gamesforwindowslive'][] = array('Games4WinLive-2', 'udp', '3074', '3074', 'both');
	$gamesplist['gamesforwindowslive'][] = array('Games4WinLive-3', 'tcp', '3074', '3074', 'both');

$gamesplist['googlestadia'] = array();
	/* Google Stadia */
	$gamesplist['googlestadia'][] = array('google-stadia-UDP-1', 'udp', '44700', '44899', 'both');
	$gamesplist['googlestadia'][] = array('google-stadia-TCP-1', 'tcp', '44700', '44899', 'both');

/* Games */

$gamesplist['arma2'] = array();
	/* ARMA 2 */
	$gamesplist['arma2'][] = array('arma2', 'udp', '2302', '2310', 'both');

$gamesplist['arma3'] = array();
	/* ARMA 3 */
	$gamesplist['arma3'][] = array('arma3-game-traffic', 'udp', '2302', '2302', 'both');
	$gamesplist['arma3'][] = array('arma3-steam-query', 'udp', '2303', '2303', 'both');
	$gamesplist['arma3'][] = array('arma3-steam-port', 'udp', '2304', '2304', 'both');
	$gamesplist['arma3'][] = array('arma3-BattleEye-1', 'tcp', '2345', '2345', 'both');
	$gamesplist['arma3'][] = array('arma3-BattleEye-2', 'tcp', '2344', '2344', 'both');
	$gamesplist['arma3'][] = array('arma3-BattleEye-2', 'udp', '2344', '2344', 'both');

$gamesplist['battlefield2'] = array();
	/* Battlefield 2 */
	$gamesplist['battlefield2'][] = array('BF2-1500-4999', 'udp', '1500', '4999', 'both');
	$gamesplist['battlefield2'][] = array('BF2-4711', 'tcp', '4711', '4711', 'both');
	$gamesplist['battlefield2'][] = array('BF2-16567', 'udp', '16567', '16567', 'both');
	$gamesplist['battlefield2'][] = array('BF2-27900', 'udp', '27900', '27900', 'both');
	$gamesplist['battlefield2'][] = array('BF2-28910', 'tcp', '28910', '28910', 'both');
	$gamesplist['battlefield2'][] = array('BF2-29900-29901-UDP', 'udp', '29900', '29901', 'both');
	$gamesplist['battlefield2'][] = array('BF2-29900-29901-TCP', 'tcp', '29900', '29901', 'both');
	$gamesplist['battlefield2'][] = array('BF2-27900', 'udp', '27900', '27900', 'both');
	$gamesplist['battlefield2'][] = array('BF2-55123-55125', 'udp', '55123', '55125', 'both');

$gamesplist['battlefield3'] = array();
	/* Battlefield 3 and Battlefield 4 */
	$gamesplist['battlefield3'][] = array('BF3-1', 'tcp', '9988', '9988', 'both');
	$gamesplist['battlefield3'][] = array('BF3-2', 'tcp', '20000', '20100', 'both');
	$gamesplist['battlefield3'][] = array('BF3-3', 'tcp', '22990', '22990', 'both');
	$gamesplist['battlefield3'][] = array('BF3-4', 'tcp', '17502', '17502', 'both');
	$gamesplist['battlefield3'][] = array('BF3-5', 'tcp', '42127', '42127', 'both');
	$gamesplist['battlefield3'][] = array('BF3-6', 'udp', '3659', '3659', 'both');
	$gamesplist['battlefield3'][] = array('BF3-7', 'udp', '14000', '14016', 'both');
	$gamesplist['battlefield3'][] = array('BF3-8', 'udp', '22990', '23006', 'both');
	$gamesplist['battlefield3'][] = array('BF3-9', 'udp', '25200', '25300', 'both');
	$gamesplist['battlefield3'][] = array('BF3-PS-1', 'tcp', '10000', '10100', 'both');
	$gamesplist['battlefield3'][] = array('BF3-PS-2', 'tcp', '1935', '1935', 'both');


$gamesplist['battlefieldbc2'] = array();
	/* Battlefield Bad Company 2 */
	$gamesplist['battlefieldbc2'][] = array('BFBC2-1', 'tcp', '18390', '18390', 'both');
	$gamesplist['battlefieldbc2'][] = array('BFBC2-2', 'tcp', '18395', '18395', 'both');
	$gamesplist['battlefieldbc2'][] = array('BFBC2-3', 'udp', '18395', '18395', 'both');
	$gamesplist['battlefieldbc2'][] = array('BFBC2-4', 'tcp', '13505', '13505', 'both');

$gamesplist['borderlands'] = array();
	/* Borderlands */
	$gamesplist['borderlands'][] = array('Borderlands-udp', 'udp', '7777', '7777', 'both');
	$gamesplist['borderlands'][] = array('Borderlands-tcp', 'tcp', '7777', '7777', 'both');

$gamesplist['callofduty'] = array();
	/* Call Of Duty */
	$gamesplist['callofduty'][] = array('CallOfDuty1', 'tcp', '28960', '28960', 'both');
	$gamesplist['callofduty'][] = array('CallOfDuty2', 'udp', '28960', '28960', 'both');

$gamesplist['counterstrike'] = array();
	/* counter strike */
	$gamesplist['counterstrike'][] = array('CS-Titan', 'udp', '6003', '6003', 'both');
	$gamesplist['counterstrike'][] = array('CS-Authentication', 'udp', '7002', '7002', 'both');
	$gamesplist['counterstrike'][] = array('CS-Client', 'udp', '6003', '6003', 'both');
	$gamesplist['counterstrike'][] = array('CS-Masterserver', 'udp', '27010', '27010', 'both');
	$gamesplist['counterstrike'][] = array('CS-Mod-Server', 'udp', '27011', '27011', 'both');
	$gamesplist['counterstrike'][] = array('CS-Chat', 'udp', '27012', '27012', 'both');
	$gamesplist['counterstrike'][] = array('CS-HL-Serverport1', 'udp', '27013', '27013', 'both');
	$gamesplist['counterstrike'][] = array('CS-HL-Serverport2', 'udp', '27014', '27014', 'both');
	$gamesplist['counterstrike'][] = array('CS-HL-Serverport', 'udp', '27015', '27015', 'both');

$gamesplist['crysis2'] = array();
	/* Crysis 2 */
	$gamesplist['crysis2'][] = array('Crysis2', 'udp', '64100', '64100', 'both');

$gamesplist['crysis3'] = array();
	/* Crysis 3 */
	$gamesplist['crysis3'][] = array('Crysis3-TCP-1', 'tcp', '9988', '9988', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-TCP-2', 'tcp', '17502', '17502', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-TCP-3', 'tcp', '25650', '25780', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-TCP-4', 'tcp', '42127', '42127', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-TCP-5', 'tcp', '64100', '64110', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-UDP-1', 'udp', '3659', '3659', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-UDP-2', 'udp', '10000', '10100', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-UDP-3', 'udp', '25650', '25780', 'both');
	$gamesplist['crysis3'][] = array('Crysis3-UDP-4', 'udp', '64100', '64110', 'both');

$gamesplist['deadspace2'] = array();
	/* Dead Space 2 */
	$gamesplist['deadspace2'][] = array('DeadSpace2-TCP-1', 'tcp', '28910', '28910', 'both');
	$gamesplist['deadspace2'][] = array('DeadSpace2-TCP-2', 'tcp', '29900', '29901', 'both');
	$gamesplist['deadspace2'][] = array('DeadSpace2-UDP-1', 'udp', '8088', '28088', 'both');

$gamesplist['deadspace3'] = array();
	/* Dead Space 3 */
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-1', 'tcp', '1024', '1124', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-2', 'tcp', '9960', '9969', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-3', 'tcp', '18000', '18000', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-4', 'tcp', '18120', '18120', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-5', 'tcp', '18060', '18060', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-6', 'tcp', '27900', '27900', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-7', 'tcp', '28910', '28910', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-TCP-8', 'tcp', '29900', '29900', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-UDP-1', 'udp', '1024', '1124', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-UDP-2', 'udp', '18000', '18000', 'both');
	$gamesplist['deadspace3'][] = array('DeadSpace3-UDP-3', 'udp', '29900', '29900', 'both');

$gamesplist['deltaforce'] = array();
	/* delta force */
	$gamesplist['deltaforce'][] = array('Delta1', 'udp', '17478', '17488', 'both');

$gamesplist['dirt3'] = array();
	/* ARMA 2 */
	$gamesplist['dirt3'][] = array('Dirt3-1', 'tcp', '2300', '2400', 'both');
	$gamesplist['dirt3'][] = array('Dirt3-2', 'udp', '2300', '2400', 'both');
	$gamesplist['dirt3'][] = array('Dirt3-3', 'udp', '6073', '6073', 'both');
	$gamesplist['dirt3'][] = array('Dirt3-4', 'tcp', '47624', '47624', 'both');

$gamesplist['doom3'] = array();
	/* doom3 */
	$gamesplist['doom3'][] = array('DOOM3-1', 'udp', '27650', '27650', 'both');
	$gamesplist['doom3'][] = array('DOOM3-2', 'udp', '27666', '27666', 'both');

$gamesplist['dragonage2'] = array();
	/* Dragon Age 2 */
	$gamesplist['dragonage2'][] = array('DragonAge2-TCP-1', 'tcp', '8000', '8000', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-TCP-2', 'tcp', '12025', '12025', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-TCP-3', 'tcp', '15101', '15325', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-TCP-4', 'tcp', '18081', '18081', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-TCP-5', 'tcp', '42127', '42127', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-UDP-1', 'udp', '1900', '1900', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-UDP-2', 'udp', '5355', '5355', 'both');
	$gamesplist['dragonage2'][] = array('DragonAge2-UDP-3', 'udp', '8001', '8001', 'both');

$gamesplist['empireearth'] = array();
	/* empire earth */
	$gamesplist['empireearth'][] = array('EmpireEarth-1', 'tcp', '33335', '33336', 'both');
	$gamesplist['empireearth'][] = array('EmpireEarth-2', 'udp', '33334', '33334', 'both');

$gamesplist['eveonline'] = array();
	/* EVE Online */
	$gamesplist['eveonline'][] = array('EVEOnline-tcp', 'tcp', '26000', '26000', 'both');
	$gamesplist['eveonline'][] = array('EVEOnline-udp', 'udp', '26000', '26000', 'both');
	$gamesplist['eveonline'][] = array('EVEOnline-alternate-tcp', 'tcp', '3724', '3724', 'both');
	$gamesplist['eveonline'][] = array('EVEOnline-alternate-udp', 'udp', '3724', '3724', 'both');

$gamesplist['everquest'] = array();
	/* everquest */
	$gamesplist['everquest'][] = array('Everquest-1', 'tcp', '1024', '6000', 'both');
	$gamesplist['everquest'][] = array('Everquest-2', 'tcp', '7000', '7000', 'both');
	$gamesplist['everquest'][] = array('Everquest-3', 'udp', '1024', '6000', 'both');
	$gamesplist['everquest'][] = array('Everquest-4', 'udp', '7000', '7000', 'both');

$gamesplist['everquest2'] = array();
	/* everquest2 */
	$gamesplist['everquest2'][] = array('Everquest2-1', 'tcp', '7000', '7000', 'both');
	$gamesplist['everquest2'][] = array('Everquest2-2', 'udp', '3016', '3021', 'both');
	$gamesplist['everquest2'][] = array('Everquest2-3', 'udp', '9100', '9100', 'both');
	$gamesplist['everquest2'][] = array('Everquest2-4', 'udp', '9700', '9703', 'both');
	$gamesplist['everquest2'][] = array('Everquest2-5', 'udp', '32800', '33000', 'both');

$gamesplist['farcry'] = array();
	/* far cry */
	$gamesplist['farcry'][] = array('FarCry-1', 'tcp', '49001', '49002', 'both');
	$gamesplist['farcry'][] = array('FarCry-2', 'udp', '49001', '49002', 'both');

$gamesplist['farcry2'] = array();
	/* FarCry 2*/
	$gamesplist['farcry2'][] = array('FarCry2-tcp', 'tcp', '9000', '9004', 'both');
	$gamesplist['farcry2'][] = array('FarCry2-udp', 'udp', '9000', '9004', 'both');

$gamesplist['farcry3'] = array();
	/* FarCry 3*/
	$gamesplist['farcry3'][] = array('FarCry3-game', 'udp', '9000', '9000', 'both');
	$gamesplist['farcry3'][] = array('FarCry3-punkbuster', 'udp', '10009', '10009', 'both');

$gamesplist['gunzonline'] = array();
	/* GunZ Online */
	$gamesplist['gunzonline'][] = array('GunZOnline', 'udp', '7700', '7700', 'both');

$gamesplist['halflife'] = array();
	/* halflife */
	$gamesplist['halflife'][] = array('HL-1', 'tcp', '27015', '27015', 'both');
	$gamesplist['halflife'][] = array('HL-2', 'udp', '27650', '27650', 'both');
	$gamesplist['halflife'][] = array('HL-3', 'udp', '27666', '27666', 'both');

$gamesplist['leagueoflegends'] = array();
	/* League of Legends */
	$gamesplist['leagueoflegends'][] = array('LeagueofLegends-1', 'udp', '5000', '5500', 'both');
	$gamesplist['leagueoflegends'][] = array('LeagueofLegends-2', 'tcp', '2099', '2099', 'both');
	$gamesplist['leagueoflegends'][] = array('LeagueofLegends-3', 'tcp', '5222', '5223', 'both');

$gamesplist['lineage2'] = array();
	/* Lineage II */
	$gamesplist['lineage2'][] = array('Lineage2-2009', 'tcp', '2009', '2009', 'both');
	$gamesplist['lineage2'][] = array('Lineage2-2106', 'tcp', '2106', '2106', 'both');
	$gamesplist['lineage2'][] = array('Lineage2-7777', 'tcp', '7777', '7777', 'both');

$gamesplist['masseffect3'] = array();
	/* MassEffect 3 */
	$gamesplist['masseffect3'][] = array('MassEffect3-UDP-1', 'udp', '5659', '5659', 'both');
	$gamesplist['masseffect3'][] = array('MassEffect3-UDP-1', 'udp', '6000', '6000', 'both');

$gamesplist['mechwarrioronline'] = array();
	/* MechWarrior: Online */
	$gamesplist['mechwarrioronline'][] = array('MechWarriorOnline-tcp1', 'tcp', '45461', '45461', 'both');
	$gamesplist['mechwarrioronline'][] = array('MechWarriorOnline-tcp2', 'tcp', '45464', '45464', 'both');
	$gamesplist['mechwarrioronline'][] = array('MechWarriorOnline-game', 'udp', '21000', '30000', 'both'); // 9000 ports

$gamesplist['minecraft'] = array();
	/* Minecraft */
	$gamesplist['minecraft'][] = array('Minecraft-tcp', 'tcp', '25565', '25565', 'both');
	$gamesplist['minecraft'][] = array('Minecraft-udp', 'udp', '25565', '25565', 'both');

$gamesplist['operationflashpoint-dr'] = array();
	/* Operation Flashpoint: Dragon Rising */
	$gamesplist['operationflashpoint-dr'][] = array('OperationFlashpoint-DR', 'udp', '9105', '9105', 'both');

$gamesplist['planetside'] = array();
	/* PlanetSide */
	$gamesplist['planetside'][] = array('PlanetSide', 'tcp', '7000', '7000', 'both');
	$gamesplist['planetside'][] = array('PlanetSide', 'tcp', '7080', '7080', 'both');
	$gamesplist['planetside'][] = array('PlanetSide2', 'udp', '3016', '3021', 'both');
	$gamesplist['planetside'][] = array('PlanetSide2', 'udp', '45000', '45010', 'both');
	$gamesplist['planetside'][] = array('PlanetSide2', 'udp', '30000', '30500', 'both');

$gamesplist['planetside2'] = array();
	/* PlanetSide 2 */
	$gamesplist['planetside2'][] = array('PlanetSide2-game', 'udp', '20040', '20199', 'both');
	$gamesplist['planetside2'][] = array('PlanetSide2-voice', 'udp', '5062', '5062', 'both');


$gamesplist['quakeiii'] = array();
	/* quake3 */
	$gamesplist['quakeiii'][] = array('Quake3', 'udp', '27910', '27919', 'both');

$gamesplist['quakeiv'] = array();
	/* quake4 */
	$gamesplist['quakeiv'][] = array('QuakeIV-server-udp', 'udp', '27650', '27650', 'both');
	$gamesplist['quakeiv'][] = array('QuakeIV-server-tcp', 'tcp', '27650', '27650', 'both');
	$gamesplist['quakeiv'][] = array('QuakeIV-client-udp', 'udp', '28004', '28004', 'both');
	$gamesplist['quakeiv'][] = array('QuakeIV-client-tcp', 'tcp', '28004', '28004', 'both');

$gamesplist['starwarstor'] = array();
	/* quake3 */
	$gamesplist['starwarstor'][] = array('StarWarsTOR-1', 'tcp', '8995', '8995', 'both');
	$gamesplist['starwarstor'][] = array('StarWarsTOR-2', 'tcp', '12000', '12999', 'both');
	$gamesplist['starwarstor'][] = array('StarWarsTOR-2', 'tcp', '20000', '30000', 'both');

$gamesplist['tigerwoods2004ps2'] = array();
	/* tiger woods 2004 ps2 */
	$gamesplist['tigerwoods2004ps2'][] = array('TigerWoods2004-Player', 'udp', '3658', '3658', 'both');
	$gamesplist['tigerwoods2004ps2'][] = array('TigerWoods2004-Player2', 'udp', '6000', '6000', 'both');
	$gamesplist['tigerwoods2004ps2'][] = array('TigerWoods2004-EA', 'tcp', '10300', '10301', 'both');

$gamesplist['tribesascend'] = array();
	/* Tribes Ascend */
	$gamesplist['tribesascend'][] = array('TribesAscend-tcp', 'tcp', '9000', '9001', 'both');
	$gamesplist['tribesascend'][] = array('TribesAscend-udp', 'udp', '9002', '9999', 'both');

$gamesplist['unrealtournament'] = array();
	/* Unreal Tournament */
	$gamesplist['unrealtournament'][] = array('UT-game-udp', 'udp', '7777', '7787', 'both');
	$gamesplist['unrealtournament'][] = array('UT-game-tcp', 'tcp', '7777', '7787', 'both');
	$gamesplist['unrealtournament'][] = array('UT-voice', 'udp', '3783', '3783', 'both');

$gamesplist['wolfet'] = array();
	/* wolfenstein enemy territory */
	$gamesplist['wolfet'][] = array('WolfET-1', 'tcp', '27960', '27960', 'both');

$gamesplist['wow'] = array();
	/* World of Warcraft */
	$gamesplist['wow'][] = array('WoW', 'tcp', '3724', '3724', 'both');
	$gamesplist['wow'][] = array('WoW-voice', 'udp', '1119', '1119', 'both');
	$gamesplist['wow'][] = array('WoW-voice', 'udp', '3724', '3724', 'both');

$voiplist = array();

   /* asterisk server / same as vonage */
$voiplist['Asterisk'] = array();
	$voiplist['Asterisk'][] = array('Asterisk', 'udp', '5060', '5069', 'both');
	$voiplist['Asterisk'][] = array('Asterisk', 'udp', '10000', '20000', 'both');

	/* VoicePulse server */
$voiplist['VoicePulse'] = array();
	$voiplist['VoicePulse'][] = array('VoicePulse', 'udp', '16384', '16482', 'both');
	$voiplist['VoicePulse'][] = array('VoicePulse', 'udp', '4569', '4569', 'both');

	/* Panasonic Hybrid PBX */
$voiplist['Panasonic'] = array();
	$voiplist['Panasonic'][] = array('Panasonic1', 'udp', '8000', '8063', 'both');
	$voiplist['Panasonic'][] = array('Panasonic2', 'udp', '9300', '9301', 'both');
	$voiplist['Panasonic'][] = array('Panasonic3', 'udp', '2747', '2747', 'both');


$p2plist = array();
	/* To add p2p clients, push Descr,Protocol,Start,End,src/dest/both onto p2plist */
	$p2plist['aimster'] = array();
		$p2plist['aimster'][] = array('Aimster', 'tcp', '7668', '7668', 'both');
	$p2plist['bittorrent'] = array();
		$p2plist['bittorrent'][] = array('BitTorrent', 'tcp', '6881', '6999', 'both');
		$p2plist['bittorrent'][] = array('BitTorrent', 'udp', '6881', '6999', 'both');
	$p2plist['buddyshare'] = array();
		$p2plist['buddyshare'][] = array('BuddyShare', 'tcp', '7788', '7788', 'both');
	$p2plist['cutemx'] = array();
		$p2plist['cutemx'][] = array('CuteMX', 'tcp', '2340', '2340', 'both');
	$p2plist['dc++'] = array();
		$p2plist['dc++'][] = array('DC++', 'tcp', '1412', '1412', 'both');
	$p2plist['dcc'] = array();
		$p2plist['dcc'][] = array('dcc', 'tcp', '6666', '6668', 'both');
	$p2plist['directconnect'] = array();
		$p2plist['directconnect'][] = array('DirectConnect', 'tcp', '412', '412', 'both');
	$p2plist['directfileexpress'] = array();
		$p2plist['directfileexpress'][] = array('DirectFileExpress', 'tcp', '1044', '1045', 'both');
	$p2plist['edonkey2000'] = array();
		$p2plist['edonkey2000'][] = array('EDonkey2000', 'tcp', '4661', '4665', 'both');
	$p2plist['fastTrack'] = array();
		$p2plist['fastTrack'][] = array('FastTrack', 'tcp', '1214', '1214', 'both');
	$p2plist['gnutella'] = array();
		$p2plist['gnutella'][] = array('Gnutella-TCP', 'tcp', '6346', '6346', 'both');
		$p2plist['gnutella'][] = array('Gnutella-UDP', 'udp', '6346', '6346', 'both');
	$p2plist['grouper'] = array();
		$p2plist['grouper'][] = array('grouper', 'tcp', '8038', '8039', 'both');
	$p2plist['hotcomm'] = array();
		$p2plist['hotcomm'][] = array('hotComm', 'tcp', '28864', '28865', 'both');
	$p2plist['hotlineconnect'] = array();
		$p2plist['hotlineconnect'][] = array('HotlineConnect', 'tcp', '5500', '5503', 'both');
	$p2plist['imesh'] = array();
		$p2plist['imesh'][] = array('iMesh', 'tcp', '4329', '4329', 'both');
	$p2plist['napster'] = array();
		$p2plist['napster'][] = array('Napster', 'tcp', '6699', '6701', 'both');
	$p2plist['opennap'] = array();
		$p2plist['opennap'][] = array('OpenNap', 'tcp', '8888', '8889', 'both');
	$p2plist['scour'] = array();
		$p2plist['scour'][] = array('Scour', 'tcp', '8311', '8311', 'both');
	$p2plist['shareaza'] = array();
		$p2plist['shareaza'][] = array('Shareaza', 'tcp', '6346', '6346', 'both');
	$p2plist['songspy'] = array();
		$p2plist['songspy'][] = array('SongSpy', 'tcp', '5190', '5190', 'both');
	$p2plist['winmx'] = array();
		$p2plist['winmx'][] = array('WinMX', 'tcp', '6699', '6699', 'both');



$othersplist = array();
	/* Unlike other areas we are posting the queue H or L or BLANK */

	/* Remote Service / Terminal emulation */

	$othersplist['appleremotedesktop'] = array();
		/* apple remote desktop */
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop1', 'tcp', '3283', '3283', 'both');
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop2', 'tcp', '5900', '5900', 'both');
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop3', 'udp', '3283', '3283', 'both');
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop4', 'udp', '5900', '5900', 'both');

	$othersplist['msrdp'] = array();
		/* MSRDP */
		$othersplist['msrdp'][] = array('MSRDP', 'tcp', '3389', '3389', 'both');

	$othersplist['pcanywhere'] = array();
		/* symantec pc anywhere */
		$othersplist['pcanywhere'][] = array('PCAnywhere-1', 'tcp', '5631', '5631', 'both');
		$othersplist['pcanywhere'][] = array('PCAnywhere-2', 'udp', '5632', '5632', 'both');

	$othersplist['vnc'] = array();
		/* virtual network control */
		$othersplist['vnc'][] = array('VNC', 'tcp', '5900', '5930', 'both');

	/* Messanger Clients */

	$othersplist['aolinstantmessenger'] = array();
		/* AIM */
		$othersplist['aolinstantmessenger'][] = array('AIM', 'tcp', '5190', '5190', 'both');

	$othersplist['facetime'] = array();
		/* Facetime */
		$othersplist['facetime'][] = array('Facetime-UDP-1', 'udp', '3478', '3479', 'both');
		$othersplist['facetime'][] = array('Facetime-TCP-1', 'tcp', '16384', '16387', 'both');
		$othersplist['facetime'][] = array('Facetime-TCP-2', 'tcp', '16393', '16402', 'both');

	$othersplist['googlehangouts'] = array();
		/* Google Hangouts */
		$othersplist['googlehangouts'][] = array('GoogleHangouts-UDP', 'udp', '19302', '19309', 'both');
		$othersplist['googlehangouts'][] = array('GoogleHangouts-TCP', 'tcp', '19305', '19309', 'both');

	$othersplist['icq'] = array();
		/* icq */
		$othersplist['icq'][] = array('ICQ1', 'tcp', '5190', '5190', 'both');
		$othersplist['icq'][] = array('ICQ2', 'udp', '5190', '5190', 'both');

	$othersplist['irc'] = array();
		/* internet relay chat */
		$othersplist['irc'][] = array('IRC', 'tcp', '6667', '6670', 'both');

	$othersplist['jabber'] = array();
		/* jabber */
		$othersplist['jabber'][] = array('IRC', 'tcp', '5222', '5222', 'both');
		$othersplist['jabber'][] = array('IRC', 'tcp', '5223', '5223', 'both');
		$othersplist['jabber'][] = array('IRC', 'tcp', '5269', '5269', 'both');

	$othersplist['msnmessenger'] = array();
		/* msn messenger */
		$othersplist['msnmessenger'][] = array('MSN1', 'tcp', '1863', '1863', 'both');
		$othersplist['msnmessenger'][] = array('MSN2', 'tcp', '6891', '6900', 'both');
		$othersplist['msnmessenger'][] = array('MSN3', 'tcp', '6901', '6901', 'both');
		$othersplist['msnmessenger'][] = array('MSN4', 'udp', '6901', '6901', 'both');

	$othersplist['teamspeak'] = array();
		/* teamspeak  */
		$othersplist['teamspeak'][] = array('TeamSpeak-1', 'tcp', '14534', '14534', 'both');
		$othersplist['teamspeak'][] = array('TeamSpeak-2', 'tcp', '51234', '51234', 'both');
		$othersplist['teamspeak'][] = array('TeamSpeak-3', 'udp', '8767', '8768', 'both');

	$othersplist['teamspeak3'] = array();
		/* teamspeak 3 */
		$othersplist['teamspeak3'][] = array('TeamSpeak3-FileTransfer', 'tcp', '30033', '30033', 'both');
		$othersplist['teamspeak3'][] = array('TeamSpeak3-ServerQuery', 'tcp', '10011', '10011', 'both');
		$othersplist['teamspeak3'][] = array('TeamSpeak3-Voice', 'udp', '9987', '9987', 'both');
		$othersplist['teamspeak3'][] = array('TeamSpeak3-TSDNS', 'tcp', '41144', '41144', 'both');

	$othersplist['ventrilo'] = array();
		/* ventrilo */
		$othersplist['ventrilo'][] = array('Ventrilo-TCP', 'tcp', '3784', '3784', 'both');
		$othersplist['ventrilo'][] = array('Ventrilo-UDP', 'udp', '3784', '3784', 'both');
		$othersplist['ventrilo'][] = array('Ventrilo-Voice', 'udp', '6100', '6100', 'both');

	/* VPN */

	$othersplist['pptp'] = array();
		/* PPTP */
		$othersplist['pptp'][] = array('PPTP', 'tcp', '1723', '1723', 'both');
		$othersplist['pptp'][] = array('PPTPGRE', 'gre', '', '', 'both');

	$othersplist['ipsec'] = array();
		/* IPSEC */
		$othersplist['ipsec'][] = array('IPSEC', 'udp', '500', '500', 'both');
		$othersplist['ipsec'][] = array('IPSEC', 'ah', '', '', 'both');
		$othersplist['ipsec'][] = array('IPSEC', 'esp', '', '', 'both');

	/* Multimedia/Streaming */

	$othersplist['itunesradio'] = array();
		/* Apple iTunes Radio Stream */
		$othersplist['itunesradio'][] = array('iTunesRadio', 'tcp', '42000', '42999', 'both');

	$othersplist['streamingmp3'] = array();
		/* streaming mp3 media aka shoutcast */
		$othersplist['streamingmp3'][] = array('STREAMINGMP3', 'tcp', '8000', '8100', 'both');

	$othersplist['rtsp'] = array();
		/* realtime streaming protocol */
		$othersplist['rtsp'][] = array('RTSP1', 'tcp', '554', '554', 'both');

	$othersplist['rtmp'] = array();
		/* Real-Time Messaging Protocol */
		$othersplist['rtmp'][] = array('RTMP', 'tcp', '1935', '1935', 'both');

	/* Web */

	$othersplist['http'] = array();
		/* HTTP aka Web Traffic */
		$othersplist['http'][] = array('HTTP', 'tcp', '80', '80', 'both');
		$othersplist['http'][] = array('HTTPS', 'tcp', '443', '443', 'both');

	/* Mail */

	$othersplist['imap'] = array();
		/* IMAP */
		$othersplist['imap'][] = array('IMAP', 'tcp', '143', '143', 'both');
		$othersplist['imap'][] = array('IMAP-Secure', 'tcp', '993', '993', 'both');

	$othersplist['lotusnotes'] = array();
		/* lotus notes */
		$othersplist['lotusnotes'][] = array('LotusNotes1', 'tcp', '1352', '1352', 'both');
		$othersplist['lotusnotes'][] = array('LotusNotes2', 'udp', '1352', '1352', 'both');

	$othersplist['pop3'] = array();
		/* Post Office Protocol - POP3 */
		$othersplist['pop3'][] = array('POP3', 'tcp', '110', '110', 'both');
		$othersplist['pop3'][] = array('POP3-Secure', 'tcp', '995', '995', 'both');

	$othersplist['smtp'] = array();
		/* SMTP */
		$othersplist['smtp'][] = array('SMTP', 'tcp', '25', '25', 'both');
		$othersplist['smtp'][] = array('SMTP-Secure-1', 'tcp', '465', '465', 'both');
		$othersplist['smtp'][] = array('SMTP-Secure-2', 'tcp', '587', '587', 'both');

	/* Game Downloader */

	//NOTE: Battle.net-Downloader runs on this port range.  Don't want that up with the game que.
	$othersplist['battlenetdownloader'] = array();
		$othersplist['battlenetdownloader'][] = array('Battle.NET-Downloader', 'tcp', '6881', '6999', 'both');

	//NOTE: steam downloads, probably don't want this in the game que
	$othersplist['steamdownloader'] = array();
		$othersplist['steamdownloader'][] = array('Steam-Downloader', 'tcp', '27014', '27050', 'both');

	/* Miscellaneous */

	$othersplist['apns'] = array();
		/* Apple Push Notification Service */
		$othersplist['apns'][] = array('APNS', 'tcp', '5223', '5223', 'both');
		$othersplist['apns'][] = array('APNS', 'tcp', '2195', '2196', 'both');

	$othersplist['applemobilesync'] = array();
		/* Apple Mobile Sync */
		$othersplist['applemobilesync'][] = array('AppleMobileSync', 'tcp', '2336', '2336', 'both');

	$othersplist['crashplan'] = array();
		/* crashplan  */
		$othersplist['crashplan'][] = array('CrashPlan-1', 'tcp', '4282', '4282', 'both');
		$othersplist['crashplan'][] = array('CrashPlan-2', 'tcp', '4285', '4285', 'both');

	$othersplist['cvsup'] = array();
		/* cvs  */
		$othersplist['cvsup'][] = array('cvsup', 'tcp', '5999', '5999', 'both');

	$othersplist['dns'] = array();
		/* domain name system */
		$othersplist['dns'][] = array('DNS1', 'tcp', '53', '53', 'both');
		$othersplist['dns'][] = array('DNS2', 'udp', '53', '53', 'both');

	$othersplist['git'] = array();
		/* GIT  */
		$othersplist['git'][] = array('git', 'tcp', '9418', '9418', 'both');

	$othersplist['hbci'] = array();
		/* HBCI  */
		$othersplist['hbci'][] = array('HBCI', 'tcp', '3000', '3000', 'both');

	$othersplist['icmp'] = array();
		/* ICMP */
		$othersplist['icmp'][] = array('ICMP', 'icmp', '', '', 'both');

	$othersplist['mysqlserver'] = array();
		/* mysql server */
		$othersplist['mysqlserver'][] = array('MySQL1', 'tcp', '3306', '3306', 'both');

	$othersplist['nntp'] = array();
		/* nntp */
		$othersplist['nntp'][] = array('NNTP1', 'tcp', '119', '119', 'both');
		$othersplist['nntp'][] = array('NNTP2', 'udp', '119', '119', 'both');

	$othersplist['slingbox'] = array();
		/* slingbox */
		$othersplist['slingbox'][] = array('Slingbox1', 'tcp', '5001', '5001', 'both');
		$othersplist['slingbox'][] = array('Slingbox2', 'udp', '5001', '5001', 'both');

	$othersplist['smb'] = array();
		/* Microsoft SMB and friends */
		$othersplist['smb'][] = array('SMB1', 'tcp', '445', '445', 'both');
		$othersplist['smb'][] = array('SMB2', 'tcp', '137', '139', 'both');

	$othersplist['snmp'] = array();
		/* Simple network management protocol */
		$othersplist['snmp'][] = array('SNMP', 'tcp', '161', '161', 'both');
		$othersplist['snmp'][] = array('SNMP2', 'udp', '161', '161', 'both');

	$othersplist['subversion'] = array();
		/* subversion  */
		$othersplist['subversion'][] = array('subversion', 'tcp', '3690', '3690', 'both');

?>
