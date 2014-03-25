<?php
/*
        part of pfSense (https://www.pfsense.org/)

        Copyright (C) 2006 Bill Marquette - bill.marquette@gmail.com.
        Copyright (C) 2006 Scott Ullrich - sullrich@pfsense.com.
        Copyright (C) 2008-2010 Ermal Luçi
        All rights reserved.

        Redistribution and use in source and binary forms, with or without
        modification, are permitted provided that the following conditions are met:

        1. Redistributions of source code must retain the above copyright notice,
           this list of conditions and the following disclaimer.

        2. Redistributions in binary form must reproduce the above copyright
           notice, this list of conditions and the following disclaimer in the
           documentation and/or other materials provided with the distribution.

        THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
        INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
        AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
        AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
        OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
        SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
        INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
        CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
        ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
        POSSIBILITY OF SUCH DAMAGE.
*/

$gamesplist = array();

$gamesplist['arma2'] = array();
	/* ARMA 2 */
	$gamesplist['arma2'][] = array('arma2', 'udp', '2302', '2310', 'both');
	
$gamesplist['battlenet'] = array();
	/* Blizzard Publishing games */
	$gamesplist['battlenet'][] = array('Battle.NET-game1-tcp', 'tcp', '6112', '6119', 'both'); //diablo, diablo2, starcraft, warcraft 2, warcraft 3
	$gamesplist['battlenet'][] = array('Battle.NET-game1-udp', 'udp', '6112', '6119', 'both'); //diablo, diablo2, starcraft, warcraft 2
	$gamesplist['battlenet'][] = array('Battle.NET-diablo2', 'tcp', '4000', '4000', 'both'); //diablo2
	$gamesplist['battlenet'][] = array('Battle.NET-game2', 'tcp', '1119', '1119', 'both');  //diablo3, starcraft 2

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
	/* Battlefield 3 */
	$gamesplist['battlefield3'][] = array('BF3-1', 'tcp', '9988', '9988', 'both');
	$gamesplist['battlefield3'][] = array('BF3-2', 'tcp', '20000', '20100', 'both');
	$gamesplist['battlefield3'][] = array('BF3-3', 'tcp', '22990', '22990', 'both');
	$gamesplist['battlefield3'][] = array('BF3-4', 'tcp', '17502', '17502', 'both');
	$gamesplist['battlefield3'][] = array('BF3-5', 'tcp', '42127', '42127', 'both');
	$gamesplist['battlefield3'][] = array('BF3-6', 'udp', '3659', '3659', 'both');
	$gamesplist['battlefield3'][] = array('BF3-7', 'udp', '14000', '14016', 'both');
	$gamesplist['battlefield3'][] = array('BF3-8', 'udp', '22990', '23006', 'both');
	$gamesplist['battlefield3'][] = array('BF3-9', 'udp', '25200', '25300', 'both');

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

$gamesplist['eveonline'] = array();
	/* EVE Online */
	$gamesplist['eveonline'][] = array('EVEOnline-tcp', 'tcp', '26000', '26000', 'both');
	$gamesplist['eveonline'][] = array('EVEOnline-udp', 'udp', '26000', '26000', 'both');
	$gamesplist['eveonline'][] = array('EVEOnline-alternate-tcp', 'tcp', '3724', '3724', 'both');
	$gamesplist['eveonline'][] = array('EVEOnline-alternate-udp', 'udp', '3724', '3724', 'both');

$gamesplist['empireearth'] = array();
	/* empire earth */
	$gamesplist['empireearth'][] = array('EmpireEarth-1', 'tcp', '33335', '33336', 'both');
	$gamesplist['empireearth'][] = array('EmpireEarth-2', 'udp', '33334', '33334', 'both');

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

$gamesplist['halo2'] = array();
	/* Halo2 + XBOX Live */
	$gamesplist['halo2'][] = array('Halo2-1', 'udp', '88', '88', 'both');
	$gamesplist['halo2'][] = array('Halo2-2', 'udp', '3074', '3074', 'both');
	$gamesplist['halo2'][] = array('Halo2-3', 'tcp', '3074', '3074', 'both');

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
	$gamesplist['operationflashpoint-dr'][] = array('operationflashpoint-dr-game', 'udp', '9105', '9105', 'both');

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
	
$gamesplist['playstation3'] = array();
	/* PlayStation 2 */
	$gamesplist['playstation3'][] = array('PS3-pshome1', 'tcp', '3478', '3480', 'both');
	$gamesplist['playstation3'][] = array('PS3-pshome2', 'tcp', '8080', '8080', 'both');
	$gamesplist['playstation3'][] = array('PS3-tcp1', 'tcp', '5223', '5223', 'both');
	$gamesplist['playstation3'][] = array('PS3-tcp2', 'tcp', '10070', '10080', 'both');
	$gamesplist['playstation3'][] = array('PS3-udp1', 'udp', '3478', '3479', 'both');
	$gamesplist['playstation3'][] = array('PS3-udp2', 'udp', '3658', '3658', 'both');
	$gamesplist['playstation3'][] = array('PS3-udp3', 'udp', '10070', '10070', 'both');
	$gamesplist['playstation3'][] = array('PS3-remoteplay', 'tcp', '9293', '9293', 'both');
	
$gamesplist['quakeiii'] = array();
	/* quake3 */
	$gamesplist['quakeiii'][] = array('quakeiii', 'udp', '27910', '27919', 'both');
	
$gamesplist['quakeiv'] = array();
	/* quake4 */
	$gamesplist['quakeiv'][] = array('quakeiv-server-udp', 'udp', '27650', '27650', 'both');
	$gamesplist['quakeiv'][] = array('quakeiv-server-tcp', 'tcp', '27650', '27650', 'both');
	$gamesplist['quakeiv'][] = array('quakeiv-client-udp', 'udp', '28004', '28004', 'both');
	$gamesplist['quakeiv'][] = array('quakeiv-client-tcp', 'tcp', '28004', '28004', 'both');

$gamesplist['starwarstor'] = array();
	/* quake3 */
	$gamesplist['starwarstor'][] = array('StarWarsTOR-1', 'tcp', '8995', '8995', 'both');
	$gamesplist['starwarstor'][] = array('StarWarsTOR-2', 'tcp', '12000', '12999', 'both');
	$gamesplist['starwarstor'][] = array('StarWarsTOR-2', 'tcp', '20000', '30000', 'both');

$gamesplist['steam'] = array();
	/* Steam Games */
	$gamesplist['steam'][] = array('steam-game-udp', 'udp', '27000', '27030', 'both');  //america's army 3, cs:s, cs:go, HL2, COD: Black Ops, COD: Black Ops 2, Natural Selection 2
	$gamesplist['steam'][] = array('steam-game-tcp', 'tcp', '27000', '27030', 'both');  //america's army 3, cs:s, cs:go, HL2, COD: Black Ops, COD: Black Ops 2, Natural Selection 2
	$gamesplist['steam'][] = array('steam-hltv', 'udp', '27015', '27030', 'both');
	$gamesplist['steam'][] = array('steam-1', 'udp', '4380', '4380', 'both');
	$gamesplist['steam'][] = array('steam-2', 'udp', '1200', '1200', 'both');
	$gamesplist['steam'][] = array('steam-voice', 'udp', '3478', '3480', 'both');
	//NOTE: steam downloads, probably don't want this in the game que
	//$gamesplist['steam'][] = array('steam-downloads', 'tcp', '27014', '27050', 'both');

$gamesplist['tigerwoods2004ps2'] = array();
	/* tiger woods 2004 ps2 */
	$gamesplist['tigerwoods2004ps2'][] = array('TigerWoods2004-Player', 'udp', '3658', '3658', 'both');
	$gamesplist['tigerwoods2004ps2'][] = array('TigerWoods2004-Player2', 'udp', '6000', '6000', 'both');
	$gamesplist['tigerwoods2004ps2'][] = array('TigerWoods2004-EA', 'tcp', '10300', '10301', 'both');

$gamesplist['tribesascend'] = array();
	/* Tribes Ascend */
	$gamesplist['tribesascend'][] = array('tribesascend-tcp', 'tcp', '9000', '9001', 'both');
	$gamesplist['tribesascend'][] = array('tribesascend-udp', 'udp', '9002', '9999', 'both');

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

$gamesplist['xbox360'] = array();
	/* XBox360 and Games for Windows Live*/
	$gamesplist['xbox360'][] = array('xbox360-1', 'udp', '88', '88', 'both');
	$gamesplist['xbox360'][] = array('xbox360-2', 'udp', '3074', '3074', 'both');
	$gamesplist['xbox360'][] = array('xbox360-3', 'tcp', '3074', '3074', 'both');


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

	$othersplist['msrdp'] = array();
		/* MSRDP */
		$othersplist['msrdp'][] = array('MSRDP', 'tcp', '3389', '3389', 'both');
	$othersplist['pptp'] = array();
		/* PPTP */
		$othersplist['pptp'][] = array('PPTP', 'tcp', '1723', '1723', 'both');
		$othersplist['pptp'][] = array('PPTPGRE', 'gre', '', '', 'both');
	$othersplist['ipsec'] = array();
		/* IPSEC */
		$othersplist['ipsec'][] = array('IPSEC', 'udp', '500', '500', 'both');
		$othersplist['ipsec'][] = array('IPSEC', 'ah', '', '', 'both');
		$othersplist['ipsec'][] = array('IPSEC', 'esp', '', '', 'both');
	$othersplist['streamingmp3'] = array();
		/* streaming mp3 media aka shoutcast */
		$othersplist['streamingmp3'][] = array('STREAMINGMP3', 'tcp', '8000', '8100', 'both');
	$othersplist['irc'] = array();
		/* internet relay chat */
		$othersplist['irc'][] = array('IRC', 'tcp', '6667', '6670', 'both');
	$othersplist['jabber'] = array();
		/* jabber */
		$othersplist['jabber'][] = array('IRC', 'tcp', '5222', '5222', 'both');
		$othersplist['jabber'][] = array('IRC', 'tcp', '5223', '5223', 'both');
		$othersplist['jabber'][] = array('IRC', 'tcp', '5269', '5269', 'both');
	$othersplist['dns'] = array();
		/* domain name system */
		$othersplist['dns'][] = array('DNS1', 'tcp', '53', '53', 'both');
		$othersplist['dns'][] = array('DNS2', 'udp', '53', '53', 'both');
	$othersplist['http'] = array();
		/* HTTP aka Web Traffic */
		$othersplist['http'][] = array('HTTP', 'tcp', '80', '80', 'both');
		$othersplist['http'][] = array('HTTPS', 'tcp', '443', '443', 'both');
	$othersplist['smtp'] = array();
		/* Secure shell traffic */
		$othersplist['smtp'][] = array('SMTP', 'tcp', '25', '25', 'both');
	$othersplist['pop3'] = array();
		/* Post Office Protocol - POP3 */
		$othersplist['pop3'][] = array('POP3', 'tcp', '110', '110', 'both');
	$othersplist['icmp'] = array();
		/* ICMP */
		$othersplist['icmp'][] = array('ICMP', 'icmp', '', '', 'both');
	$othersplist['imap'] = array();
		/* IMAP */
		$othersplist['imap'][] = array('IMAP', 'tcp', '143', '143', 'both');
	$othersplist['smb'] = array();
		/* Microsoft SMB and friends */
		$othersplist['smb'][] = array('SMB1', 'tcp', '445', '445', 'both');
		$othersplist['smb'][] = array('SMB2', 'tcp', '137-139', '137-139', 'both');
	$othersplist['rtsp'] = array();
		/* realtime streaming protocol */
		$othersplist['rtsp'][] = array('RTSP1', 'tcp', '554', '554', 'both');
	$othersplist['snmp'] = array();
		/* Simple network management protocol */
		$othersplist['snmp'][] = array('SNMP', 'tcp', '161', '161', 'both');
		$othersplist['snmp'][] = array('SNMP2', 'udp', '161', '161', 'both');
	$othersplist['vnc'] = array();
		/* virtual network control */
		$othersplist['vnc'][] = array('VNC', 'tcp', '5900', '5930', 'both');
	$othersplist['appleremotedesktop'] = array();
		/* apple remote desktop */
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop1', 'tcp', '3283', '3283', 'both');
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop2', 'tcp', '5900', '5900', 'both');
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop3', 'udp', '3283', '3283', 'both');
		$othersplist['appleremotedesktop'][] = array('AppleRemoteDesktop4', 'udp', '5900', '5900', 'both');
	$othersplist['icq'] = array();
		/* icq */
		$othersplist['icq'][] = array('ICQ1', 'tcp', '5190', '5190', 'both');
		$othersplist['icq'][] = array('ICQ2', 'udp', '5190', '5190', 'both');
	$othersplist['lotusnotes'] = array();
		/* lotus notes */
		$othersplist['lotusnotes'][] = array('LotusNotes1', 'tcp', '1352', '1352', 'both');
		$othersplist['lotusnotes'][] = array('LotusNotes2', 'udp', '1352', '1352', 'both');
	$othersplist['aolinstantmessenger'] = array();
		/* AIM */
		$othersplist['aolinstantmessenger'][] = array('AIM', 'tcp', '5190', '5190', 'both');
	$othersplist['msnmessenger'] = array();
		/* msn messenger */
		$othersplist['msnmessenger'][] = array('MSN1', 'tcp', '1863', '1863', 'both');
		$othersplist['msnmessenger'][] = array('MSN2', 'tcp', '6891', '6900', 'both');
		$othersplist['msnmessenger'][] = array('MSN3', 'tcp', '6901', '6901', 'both');
		$othersplist['msnmessenger'][] = array('MSN4', 'udp', '6901', '6901', 'both');
	$othersplist['mysqlserver'] = array();
		/* mysql server */
		$othersplist['mysqlserver'][] = array('MySQL1', 'tcp', '3306', '3306', 'both');
	$othersplist['nntp'] = array();
		/* nntp */
		$othersplist['nntp'][] = array('NNTP1', 'tcp', '119', '119', 'both');
		$othersplist['nntp'][] = array('NNTP2', 'udp', '119', '119', 'both');
	$othersplist['pcanywhere'] = array();
		/* symantec pc anywhere */
		$othersplist['pcanywhere'][] = array('pcany1', 'tcp', '5631', '5631', 'both');
		$othersplist['pcanywhere'][] = array('pcany2', 'udp', '5632', '5632', 'both');
	$othersplist['teamspeak'] = array();
		/* teamspeak  */
		$othersplist['teamspeak'][] = array('teamspeak1', 'tcp', '14534', '14534', 'both');
		$othersplist['teamspeak'][] = array('teamspeak2', 'tcp', '51234', '51234', 'both');
		$othersplist['teamspeak'][] = array('teamspeak3', 'udp', '8767', '8768', 'both');
	$othersplist['cvsup'] = array();
		/* cvs  */
		$othersplist['cvsup'][] = array('cvsup', 'tcp', '5999', '5999', 'both');
	$othersplist['hbci'] = array();
		/* HBCI  */
		$othersplist['hbci'][] = array('HBCI', 'tcp', '3000', '3000', 'both');
	$othersplist['slingbox'] = array();
		$othersplist['slingbox'][] = array('Slingbox1', 'tcp', '5001', '5001', 'both');
		$othersplist['slingbox'][] = array('Slingbox2', 'udp', '5001', '5001', 'both');


?>
