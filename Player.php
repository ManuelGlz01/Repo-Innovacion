<?php

while (1) {

	require _DIR_ . '/lib/conf_loader.php';

	echo "\n\nCargando el playlist...\n\n";

	echo "\n-------------------";
	echo "\nRadio:" . ($is_radio ? 'Si' : 'No');
	echo "\n-------------------";

	$play_list = json_decode(file_get_contents(PLAYLIST_FILE));

	if ($play_list == '') {
		echo "Error en el playlist...\n";
		sleep(30);
		continue;
	}

	$total_items = count($play_list);

	$play_items = 0;
	foreach ($play_list as $item) {
		if ($item->play == 1) $play_items++;
	}

	if ($play_items == 0) {
		sleep(30);
		continue;
	}

	foreach ($play_list as $item) {

		$state = getState();
		//TODO CHECAR EKL INDICE DE INICIO EN EL PLAYLIST
		if ($item->index < ($state->playing->index % ($total_items + 1))) {
			echo "saltando archivo " . $item->index . "-" . $item->name . "......\n";
			continue;
		}

		$state->playing->index        = $item->index;
		$state->playing->file  		  = $item->SHA_FILE;
		$state->playing->name  		  = $item->name;
		$state->playing->grab_screens = $item->grab_screens;
		$state->playing->duration	  = $item->duration;
		setState($state);

		$dt_start	= new DateTime($item->DT_START . ' ' . $item->TIME_START);
		$dt_end		= new DateTime($item->DT_END . ' ' . $item->TIME_END);

		$time_start	= new DateTime(date('Y-m-d') . ' ' . $item->TIME_START);
		$time_end	= new DateTime(date('Y-m-d') . ' ' . $item->TIME_END);

		$now		= new DateTime("now");


		if ($dt_start > $now || $dt_end < $now || $time_start > $now || $time_end < $now || $item->play == 0 || !playToday($item->DOW)) {
			echo "saltando archivo " . $item->index . "-" . $item->name . "......\n";
			continue;
		}

		$hora = (int) date("H");

		$conf = json_decode(file_get_contents(CONFIG_FILE));

		$vid_prob = rand(0, 100);

		if (
			$conf->screens->video_enabled &&
			$vid_prob <= $conf->screens->video_probability &&
			$hora >= $conf->screens->start &&
			$hora < $conf->screens->end &&
			$item->priority < 3
		) {

			$cam_command = 'sudo avconv  -s 800x600  -i /dev/video0  -q:v 3 -t ' . round($item->duration) . ' /home/pi/storage/' . $credentials->uri . '-' . $item->SHA_FILE . '-$(date  \'+%y%m%d%H%M%S\').avi';
			$cam_url 	 = 'http://192.168.69.96/?action=exec_command&command=' . urlencode($cam_command);

			noWaitWebCall($cam_url);
		}

		$vol = buildVolume($conf->volume->base, $conf->volume->master, $item->volume, explode(',', $conf->volume->dyn)[$hora]);

		echo "Reproduciendo archivo " . $item->index . "-" . $item->name . " vol(" . $vol . ")\n";
		exec("amixer -c 1 sset Headphone " . $vol . "%");

		if ($is_radio)	$player_command = "cvlc --no-osd --alsa-audio-device hw:1,0  --fullscreen --play-and-exit " . _DIR_ . "/data/MEDIA/" . $item->URI_AUDIO . ".mp3";
		else 			$player_command = "cvlc --no-osd --alsa-audio-device hw:1,0  --fullscreen --play-and-exit " . _DIR_ . "/data/MEDIA/" . $item->SHA_FILE . ".mp4";
		//
		echo $player_command;

		exec("echo \"" . $item->SHA_FILE . ";$(date  '+%y-%m-%d %H:%M:%S')\">>" . _DIR_ . "/lazy/" . $credentials->uri . "-$(date  '+%y%m%d').plog;");
		exec($player_command);

		pcntl_signal_dispatch();

		if (checkCommands())
			continue 2;
	}

	$state = getState();
	$state->playing->index = 0;
	setState($state);
}

function checkCommands()
{

	if (file_exists(COMMAND_FILE)) {

		$command_array = explode(':', file_get_contents(COMMAND_FILE));
		@unlink(COMMAND_FILE);
		$state = getState();

		switch ($command_array[0]) {
			case "GOTO":
				$state->playing->index = $command_array[1];
				echo "\n\nBuscando el archivo con indice: " . $command_array[1] . "\n\n";
				setState($state);
				return TRUE;
				break;
			case "KILL_ME":
				die("TERMINANDO POR COMANDO");
				break;
			default:
				return FALSE;
				break;
		}
	} else {
		return FALSE;
	}
}
