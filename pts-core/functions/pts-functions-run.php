<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008, Phoronix Media
	Copyright (C) 2008, Michael Larabel
	pts-functions-run.php: Functions needed for running tests/suites.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

function pts_prompt_results_identifier($current_identifiers = null)
{
	// Prompt for a results identifier
	$RESULTS_IDENTIFIER = null;
	$show_identifiers = array();

	if(!IS_BATCH_MODE || pts_read_user_config(P_OPTION_BATCH_PROMPTIDENTIFIER, "TRUE") == "TRUE")
	{
		if(is_array($current_identifiers) && count($current_identifiers) > 0)
		{
			foreach($current_identifiers as $identifier)
			{
				if(is_array($identifier))
				{
					foreach($identifier as $identifier_2)
					{
						array_push($show_identifiers, $identifier_2);
					}
				}
				else
				{
					array_push($show_identifiers, $identifier);
				}
			}

			$show_identifiers = array_unique($show_identifiers);
			sort($show_identifiers);

			echo "\nCurrent Test Identifiers:\n";
			foreach($show_identifiers as $identifier)
			{
				echo "- " . $identifier . "\n";
			}
			echo "\n";
		}

		$times_tried = 0;
		do
		{
			if($times_tried == 0 && ($env_identifier = getenv("TEST_RESULTS_IDENTIFIER")) != false)
			{
				$RESULTS_IDENTIFIER = $env_identifier;
				echo "Test Identifier: " . $RESULTS_IDENTIFIER . "\n";
			}
			else
			{
				echo "Enter a unique name for this test run: ";
				$RESULTS_IDENTIFIER = trim(str_replace(array("/"), "", fgets(STDIN)));
			}
			$times_tried++;
		}
		while(empty($RESULTS_IDENTIFIER) || in_array($RESULTS_IDENTIFIER, $show_identifiers));
	}

	if(empty($RESULTS_IDENTIFIER))
	{
		$RESULTS_IDENTIFIER = date("Y-m-d H:i");
	}
	else
	{
		$RESULTS_IDENTIFIER = pts_swap_user_variables($RESULTS_IDENTIFIER);
	}

	pts_set_assignment_once("TEST_RESULTS_IDENTIFIER", $RESULTS_IDENTIFIER);

	return $RESULTS_IDENTIFIER;
}
function pts_prompt_test_options($identifier)
{
	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
	$test_title = $xml_parser->getXMLValue(P_TEST_TITLE);

	$USER_ARGS = "";
	$TEXT_ARGS = "";
	$test_options = pts_test_options($identifier);

	if(count($test_options) > 0)
	{
		echo pts_string_header("Test Configuration: " . $test_title);
	}

	for($this_option_pos = 0; $this_option_pos < count($test_options); $this_option_pos++)
	{
		$o = $test_options[$this_option_pos];
		$option_count = $o->option_count();

		if($option_count == 0)
		{
			// User inputs their option
			do
			{
				echo "\n" . $o->get_name() . "\n" . "Enter Value: ";
				$value = strtolower(trim(fgets(STDIN)));
			}
			while(empty($value));

			$USER_ARGS .= $o->get_option_prefix() . $value . $o->get_option_postfix();
		}
		else
		{
			if($option_count == 1)
			{
				// Only one option in menu, so auto-select it
				$bench_choice = 1;
			}
			else
			{
				// Have the user select the desired option
				echo "\n" . $o->get_name() . ":\n";
				$all_option_names = $o->get_all_option_names();
				$first_try = true;

				do
				{
					echo "\n";
					for($i = 0; $i < $option_count; $i++)
					{
						echo ($i + 1) . ": " . $o->get_option_name($i) . "\n";
					}
					echo "\nPlease Enter Your Choice: ";

					if($first_try && ($auto_opt = getenv(strtoupper($identifier) . "_" . $this_option_pos)) != false)
					{
						$bench_choice = $auto_opt;
						echo $bench_choice . "\n";
					}
					else
					{
						$bench_choice = trim(fgets(STDIN));
					}
					$first_try = false;
				}
				while(($bench_choice < 1 || $bench_choice > $option_count) && !in_array($bench_choice, $all_option_names));

				if(!is_numeric($bench_choice) && in_array($bench_choice, $all_option_names))
				{
					$match_made = false;

					for($i = 0; $i < $option_count && !$match_made; $i++)
					{
						if($o->get_option_name($i) == $bench_choice)
						{
							$bench_choice = ($i + 1);
							$match_made = true;
						}
					}
				}
			}

			// Format the selected option
			$option_display_name = $o->get_option_name(($bench_choice - 1));

			if(($cut_point = strpos($option_display_name, "(")) > 1 && strpos($option_display_name, ")") > $cut_point)
			{
				$option_display_name = substr($option_display_name, 0, $cut_point);
			}

			if(count($test_options) > 1)
			{
				$TEXT_ARGS .= $o->get_name() . ": ";
			}
			$TEXT_ARGS .= $option_display_name;

			if($this_option_pos < (count($test_options) - 1))
			{
				$TEXT_ARGS .= " - ";
			}

			$USER_ARGS .= $o->get_option_prefix() . $o->get_option_value(($bench_choice - 1)) . $o->get_option_postfix() . " ";
		}
	}

	return array($USER_ARGS, $TEXT_ARGS);
}
function pts_swap_user_variables($user_str)
{
	if(strpos($user_str, "$") !== false)
	{
		foreach(pts_user_runtime_variables() as $key => $value)
		{
			$user_str = str_replace("$" . $key, $value, $user_str);
		}
	}

	return $user_str;
}
function pts_prompt_save_file_name($check_env = true)
{
	// Prompt to save a file when running a test
	if($check_env && ($save_name = getenv("TEST_RESULTS_NAME")) != false)
	{
		$CUSTOM_TITLE = $save_name;
		$PROPOSED_FILE_NAME = pts_input_string_to_identifier($save_name);
		echo "Saving Results To: " . $PROPOSED_FILE_NAME . "\n";
	}
	else
	{
		if(!IS_BATCH_MODE || pts_read_user_config(P_OPTION_BATCH_PROMPTSAVENAME, "FALSE") == "TRUE")
		{
			$is_reserved_word = false;

			do
			{
				if($is_reserved_word)
				{
					echo "\n\nThe name of the saved file cannot be the same as a test/suite: " . $PROPOSED_FILE_NAME . "\n";
					$is_reserved_word = false;
				}

				echo "Enter a name to save these results: ";
				$PROPOSED_FILE_NAME = trim(fgets(STDIN));
				$CUSTOM_TITLE = $PROPOSED_FILE_NAME;
				$PROPOSED_FILE_NAME = pts_input_string_to_identifier($PROPOSED_FILE_NAME);

				$is_reserved_word = pts_is_test($PROPOSED_FILE_NAME) || pts_is_suite($PROPOSED_FILE_NAME);
			}
			while(empty($PROPOSED_FILE_NAME) || $is_reserved_word);
		}
		else
		{
			$PROPOSED_FILE_NAME = "";
		}
	}

	if(!isset($PROPOSED_FILE_NAME) || empty($PROPOSED_FILE_NAME))
	{
		$PROPOSED_FILE_NAME = date("Y-m-d-Hi");
	}
	if(!isset($PROPOSED_FILE_NAME) || empty($CUSTOM_TITLE))
	{
		$CUSTOM_TITLE = $PROPOSED_FILE_NAME;
	}

	return array($PROPOSED_FILE_NAME, $CUSTOM_TITLE);
}
function pts_promt_user_tags($default_tags = "")
{
	$tags_input = "";

	if(!IS_BATCH_MODE)
	{
		echo "\nTags are optional and used on Phoronix Global for making it easy to share, search, and organize test results. Example tags could be the type of test performed (i.e. WINE tests) or the hardware used (i.e. Dual Core SMP).\n\nEnter the tags you wish to provide (separated by commas): ";
		$tags_input .= fgets(STDIN);

		if(function_exists("preg_replace"))
		{
			$tags_input = preg_replace("/[^a-zA-Z0-9s, -]/", "", $tags_input);
		}

		$tags_input = trim($tags_input);
	}

	if(empty($tags_input))
	{
		if(!is_array($default_tags) && !empty($default_tags))
		{
			$default_tags = array($default_tags);
		}

		$tags_input = pts_global_auto_tags($default_tags);
	}

	return $tags_input;
}
function pts_add_test_note($note)
{
	pts_test_note("ADD", $note);
}
function pts_test_note($process, $value = null)
{
	static $note_r;
	$return = null;

	if(empty($note_r))
	{
		$note_r = array();
	}

	switch($process)
	{
		case "ADD":
			if(!empty($value) && !in_array($value, $note_r))
			{
				array_push($note_r, $value);
			}
			break;
		case "TO_STRING":
			$return = implode(". \n", $note_r);
			break;
	}

	return $return;
}
function pts_generate_test_notes($test_type)
{
	static $check_processes = null;

	if(empty($check_processes) && is_file(STATIC_DIR . "process-reporting-checks.txt"))
	{
		$word_file = trim(file_get_contents(STATIC_DIR . "process-reporting-checks.txt"));
		$processes_r = array_map("trim", explode("\n", $word_file));
		$check_processes = array();

		foreach($processes_r as $p)
		{
			$p = explode("=", $p);
			$p_title = trim($p[0]);
			$p_names = array_map("trim", explode(",", $p[1]));

			$check_processes[$p_title] = array();

			foreach($p_names as $p_name)
			{
				array_push($check_processes[$p_title], $p_name);
			}
		}
	}

	if(!IS_BSD)
	{
		pts_add_test_note(pts_process_running_string($check_processes));
	}

	// Check if Security Enhanced Linux was enforcing, permissive, or disabled
	if(is_file("/etc/sysconfig/selinux") && is_readable("/boot/grub/menu.lst"))
	{
		$selinux_file = file_get_contents("/etc/sysconfig/selinux");
		if(stripos($selinux_file, "selinux=disabled") === false)
		{
			pts_add_test_note("SELinux was enabled.");
		}
	}
	else if(is_file("/boot/grub/menu.lst") && is_readable("/boot/grub/menu.lst"))
	{
		$grub_file = file_get_contents("/boot/grub/menu.lst");
		if(stripos($grub_file, "selinux=1") !== false)
		{
			pts_add_test_note("SELinux was enabled.");
		}
	}

	// Power Saving Technologies?
	pts_add_test_note(hw_cpu_power_savings_enabled());
	pts_add_test_note(hw_sys_power_mode());
	pts_add_test_note(sw_os_virtualized_mode());

	if($test_type == "Graphics" || $test_type == "System")
	{
		$aa_level = hw_gpu_aa_level();
		$af_level = hw_gpu_af_level();

		if(!empty($aa_level))
		{
			pts_add_test_note("Antialiasing: " . $aa_level);
		}
		if(!empty($af_level))
		{
			pts_add_test_note("Anisotropic Filtering: " . $af_level);
		}
	}

	return pts_test_note("TO_STRING");
}
function pts_input_string_to_identifier($input)
{
	$input = pts_swap_user_variables($input);
	$input = trim(str_replace(array(' ', '/', '&', '\''), "", strtolower($input)));

	return $input;
}
function pts_verify_test_installation($TO_RUN)
{
	// Verify a test is installed
	$tests = pts_contained_tests($TO_RUN);
	$needs_installing = array();

	foreach($tests as $test)
	{
		if(!is_file(TEST_ENV_DIR . $test . "/pts-install.xml"))
		{
			if(!pts_test_architecture_supported($test) || !pts_test_platform_supported($test))
			{
				array_push($needs_installing, $test);
			}
		}
		else
		{
			pts_set_assignment_once("TEST_INSTALL_PASS", true);
		}
	}

	if(count($needs_installing) > 0)
	{
		$needs_installing = array_unique($needs_installing);
	
		if(count($needs_installing) == 1)
		{
			echo pts_string_header($needs_installing[0] . " isn't installed on this system.\nTo install this test, run: phoronix-test-suite install " . $needs_installing[0]);
		}
		else
		{
			$message = "Multiple tests need to be installed before proceeding:\n\n";
			foreach($needs_installing as $single_package)
			{
				$message .= "- " . $single_package . "\n";
			}

			$message .= "\nTo install these tests, run: phoronix-test-suite install " . $TO_RUN;

			echo pts_string_header($message);
		}

		if(!pts_is_assignment("TEST_INSTALL_PASS") && pts_read_assignment("COMMAND") != "benchmark")
		{
			pts_exit();
		}
	}
}
function pts_recurse_call_tests($tests_to_run, $arguments_array, $save_results = false, &$tandem_xml = "", $results_identifier = "", $arguments_description = "")
{
	// Call the tests
	if(!pts_is_assignment("PTS_RECURSE_CALL"))
	{
		pts_module_process("__pre_run_process", $tests_to_run);
		pts_set_assignment("PTS_RECURSE_CALL", 1);
	}

	for($i = 0; $i < count($tests_to_run); $i++)
	{
		if(pts_is_suite($tests_to_run[$i]))
		{
			$xml_parser = new tandem_XmlReader(pts_location_suite($tests_to_run[$i]));
			$tests_in_suite = $xml_parser->getXMLArrayValues(P_SUITE_TEST_NAME);
			$sub_arguments = $xml_parser->getXMLArrayValues(P_SUITE_TEST_ARGUMENTS);
			$sub_arguments_description = $xml_parser->getXMLArrayValues(P_SUITE_TEST_DESCRIPTION);

			pts_recurse_call_tests($tests_in_suite, $sub_arguments, $save_results, $tandem_xml, $results_identifier, $sub_arguments_description);
		}
		else if(pts_is_test($tests_to_run[$i]))
		{
			$test_result = pts_run_test($tests_to_run[$i], $arguments_array[$i], $arguments_description[$i]);
			$end_result = $test_result->get_result();

			if($save_results && count($test_result) > 0 && ((is_numeric($end_result) && $end_result > 0) || (!is_numeric($end_result) && strlen($end_result) > 2)))
			{
				pts_record_test_result($tandem_xml, $test_result, $results_identifier, pts_request_new_id());
			}

			if($i != (count($tests_to_run) - 1))
			{
				sleep(pts_read_user_config(P_OPTION_TEST_SLEEPTIME, 5));
			}
		}
	}
}
function pts_record_test_result(&$tandem_xml, $result, $identifier, $tandem_id = 128)
{
	// Do the actual recording of the test result and other relevant information for the given test

	$tandem_xml->addXmlObject(P_RESULTS_TEST_TITLE, $tandem_id, $result->get_attribute("TEST_TITLE"));
	$tandem_xml->addXmlObject(P_RESULTS_TEST_VERSION, $tandem_id, $result->get_attribute("TEST_VERSION"));
	$tandem_xml->addXmlObject(P_RESULTS_TEST_ATTRIBUTES, $tandem_id, $result->get_attribute("TEST_DESCRIPTION"));
	$tandem_xml->addXmlObject(P_RESULTS_TEST_SCALE, $tandem_id, $result->get_result_scale());
	$tandem_xml->addXmlObject(P_RESULTS_TEST_PROPORTION, $tandem_id, $result->get_result_proportion());
	$tandem_xml->addXmlObject(P_RESULTS_TEST_RESULTFORMAT, $tandem_id, $result->get_result_format());
	$tandem_xml->addXmlObject(P_RESULTS_TEST_TESTNAME, $tandem_id, $result->get_attribute("TEST_IDENTIFIER"));
	$tandem_xml->addXmlObject(P_RESULTS_TEST_ARGUMENTS, $tandem_id, $result->get_attribute("EXTRA_ARGUMENTS"));
	$tandem_xml->addXmlObject(P_RESULTS_RESULTS_GROUP_IDENTIFIER, $tandem_id, $identifier, 5);
	$tandem_xml->addXmlObject(P_RESULTS_RESULTS_GROUP_VALUE, $tandem_id, $result->get_result(), 5);
	$tandem_xml->addXmlObject(P_RESULTS_RESULTS_GROUP_RAW, $tandem_id, $result->get_trial_results_string(), 5);

	pts_set_assignment("TEST_RAN", true);
}
function pts_save_test_file($PROPOSED_FILE_NAME, &$RESULTS = null, $RAW_TEXT = null)
{
	// Save the test file
	$j = 1;
	while(is_file(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/test-" . $j . ".xml"))
	{
		$j++;
	}

	$REAL_FILE_NAME = $PROPOSED_FILE_NAME . "/test-" . $j . ".xml";

	if($RESULTS != null)
	{
		$R_FILE = $RESULTS->getXML();
	}
	else if($RAW_TEXT != null)
	{
		$R_FILE = $RAW_TEXT;
	}
	else
	{
		return false;
	}

	pts_save_result($REAL_FILE_NAME, $R_FILE);

	if(!is_file(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml"))
	{
		pts_save_result($PROPOSED_FILE_NAME . "/composite.xml", file_get_contents(SAVE_RESULTS_DIR . $REAL_FILE_NAME));
	}
	else
	{
		// Merge Results
		$MERGED_RESULTS = pts_merge_test_results(file_get_contents(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml"), file_get_contents(SAVE_RESULTS_DIR . $REAL_FILE_NAME));
		pts_save_result($PROPOSED_FILE_NAME . "/composite.xml", $MERGED_RESULTS);
	}
	return $REAL_FILE_NAME;
}
function pts_run_test($test_identifier, $extra_arguments = "", $arguments_description = "")
{
	// Do the actual test running process
	$pts_test_result = new pts_test_result();

	if(pts_process_active($test_identifier))
	{
		echo "\nThis test (" . $test_identifier . ") is already running... Please wait until the first instance is finished.\n";
		return $pts_test_result;
	}
	pts_process_register($test_identifier);
	$test_directory = TEST_ENV_DIR . $test_identifier . "/";

	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($test_identifier));
	$execute_binary = $xml_parser->getXMLValue(P_TEST_EXECUTABLE);
	$test_title = $xml_parser->getXMLValue(P_TEST_TITLE);
	$test_version = $xml_parser->getXMLValue(P_TEST_VERSION);
	$times_to_run = intval($xml_parser->getXMLValue(P_TEST_RUNCOUNT));
	$ignore_first_run = $xml_parser->getXMLValue(P_TEST_IGNOREFIRSTRUN);
	$pre_run_message = $xml_parser->getXMLValue(P_TEST_PRERUNMSG);
	$post_run_message = $xml_parser->getXMLValue(P_TEST_POSTRUNMSG);
	$result_scale = $xml_parser->getXMLValue(P_TEST_SCALE);
	$result_proportion = $xml_parser->getXMLValue(P_TEST_PROPORTION);
	$result_format = $xml_parser->getXMLValue(P_TEST_RESULTFORMAT);
	$result_quantifier = $xml_parser->getXMLValue(P_TEST_QUANTIFIER);
	$arg_identifier = $xml_parser->getXMLArrayValues(P_TEST_OPTIONS_IDENTIFIER);
	$execute_path = $xml_parser->getXMLValue(P_TEST_POSSIBLEPATHS);
	$default_arguments = $xml_parser->getXMLValue(P_TEST_DEFAULTARGUMENTS);
	$test_type = $xml_parser->getXMLValue(P_TEST_HARDWARE_TYPE);
	$root_required = $xml_parser->getXMLValue(P_TEST_ROOTNEEDED) == "TRUE";

	if(($test_type == "Graphics" && getenv("DISPLAY") == false) || getenv("NO_" . strtoupper($test_type) . "_TESTS") != false)
	{
		return $pts_test_result;
	}

	if(empty($result_format))
	{
		$result_format = "BAR_GRAPH";
	}
	else if(strlen($result_format) > 6 && substr($result_format, 0, 6) == "MULTI_") // Currently tests that output multiple results in one run can only be run once
	{
		$times_to_run = 1;
	}
	
	if(empty($times_to_run) || !is_int($times_to_run))
	{
		$times_to_run = 3;
	}

	if(!empty($test_type))
	{
		$test_name = "TEST_" . strtoupper($test_type);
		pts_set_assignment_once($test_name, 1);
	}

	if(empty($execute_binary))
	{
		$execute_binary = $test_identifier;
	}

	$execute_path_check = explode(",", $execute_path);
	array_push($execute_path_check, $test_directory);

	while(count($execute_path_check) > 0)
	{
		$path_check = trim(array_pop($execute_path_check));

		if(is_file($path_check . $execute_binary) || is_link($path_check . $execute_binary))
		{
			$to_execute = $path_check;
		}	
	}

	if(!isset($to_execute) || empty($to_execute))
	{
		echo "The test executable for " . $test_identifier . " could not be found. Skipping test.\n\n";
		return $pts_test_result;
	}

	if(pts_test_needs_updated_install($test_identifier))
	{
		echo pts_string_header("NOTE: This test installation is out of date.\nFor best results, the " . $test_title . " test should be re-installed.");
		// Auto reinstall
		//require_once("pts-core/functions/pts-functions-run.php");
		//pts_install_test($test_identifier);
	}

	$pts_test_arguments = trim($default_arguments . " " . str_replace($default_arguments, "", $extra_arguments));
	$extra_runtime_variables = pts_run_additional_vars($test_identifier);

	// Start
	$pts_test_result->set_attribute("TEST_TITLE", $test_title);
	$pts_test_result->set_attribute("TEST_IDENTIFIER", $test_identifier);
	pts_module_process("__pre_test_run", $pts_test_result);

	$time_test_start = time();
	echo pts_call_test_script($test_identifier, "pre", "\nRunning Pre-Test Scripts...\n", $test_directory, $extra_runtime_variables);

	pts_user_message($pre_run_message);

	$runtime_identifier = pts_unique_runtime_identifier();

	$execute_binary_prepend = "";

	if($root_required)
	{
		$execute_binary_prepend = TEST_LIBRARIES_DIR . "root-access.sh";
	}

	if(!empty($execute_binary_prepend))
	{
		$execute_binary_prepend .= " ";
	}

	for($i = 0; $i < $times_to_run; $i++)
	{
		$benchmark_log_file = TEST_ENV_DIR . $test_identifier . "/" . $test_identifier . "-" . $runtime_identifier . "-" . ($i + 1) . ".log";
		$start_timer = TEST_LIBRARIES_DIR . "timer-start.sh";
		$stop_timer = TEST_LIBRARIES_DIR . "timer-stop.sh";
		$timed_kill = TEST_LIBRARIES_DIR . "timed-kill.sh";
		$test_extra_runtime_variables = array_merge($extra_runtime_variables, array("LOG_FILE" => $benchmark_log_file, "TIMER_START" => $start_timer, "TIMER_STOP" => $stop_timer, "TIMED_KILL" => $timed_kill, "PHP_BIN" => PHP_BIN));

		echo pts_string_header($test_title . " (Run " . ($i + 1) . " of " . $times_to_run . ")");
		$result_output = array();

		echo $test_results = pts_exec("cd " . $to_execute . " && " . $execute_binary_prepend . "./" . $execute_binary . " " . $pts_test_arguments, $test_extra_runtime_variables);

		if(is_file($benchmark_log_file) && trim($test_results) == "")
		{
			echo file_get_contents($benchmark_log_file);
		}

		if(!($i == 0 && pts_string_bool($ignore_first_run) && $times_to_run > 1))
		{
			$test_extra_runtime_variables_post = $test_extra_runtime_variables;
			if(is_file(TEST_ENV_DIR . $test_identifier . "/pts-timer"))
			{
				$run_time = trim(file_get_contents(TEST_ENV_DIR . $test_identifier . "/pts-timer"));
				unlink(TEST_ENV_DIR . $test_identifier . "/pts-timer");

				if(is_numeric($run_time))
				{
					$test_extra_runtime_variables_post = array_merge($test_extra_runtime_variables_post, array("TIMER_RESULT" => $run_time));
				}
			}
			if(is_file($benchmark_log_file))
			{
				$test_results = "";
			}

			$test_results = pts_call_test_script($test_identifier, "parse-results", null, $test_results, $test_extra_runtime_variables_post);

			if(empty($test_results) && isset($run_time) && is_numeric($run_time))
			{
				$test_results = $run_time;
			}

			$validate_result = trim(pts_call_test_script($test_identifier, "validate-result", null, $test_results, $test_extra_runtime_variables_post));

			if(!empty($validate_result) && !pts_string_bool($validate_result))
			{
				$test_results = null;
			}

			if(!empty($test_results))
			{
				$pts_test_result->add_trial_run_result(trim($test_results));
			}
		}
		if($times_to_run > 1 && $i < ($times_to_run - 1))
		{
			pts_module_process("__interim_test_run", $pts_test_result);
			sleep(1); // Rest for a moment between tests
		}

		if(is_file($benchmark_log_file))
		{
			if(pts_is_assignment("TEST_RESULTS_IDENTIFIER") && (pts_string_bool(pts_read_user_config(P_OPTION_LOG_BENCHMARKFILES, "FALSE")) || pts_read_assignment("IS_PCQS_MODE") != false || getenv("SAVE_BENCHMARK_LOGS") != false))
			{
				$backup_log_dir = SAVE_RESULTS_DIR . pts_read_assignment(SAVE_FILE_NAME) . "/benchmark-logs/" . pts_read_assignment("TEST_RESULTS_IDENTIFIER") . "/";
				$backup_filename = basename($benchmark_log_file);
				@mkdir($backup_log_dir, 0777, true);
				@copy($benchmark_log_file, $backup_log_dir . $backup_filename);
			}

			@unlink($benchmark_log_file);
		}
	}

	echo pts_call_test_script($test_identifier, "post", null, $test_directory, $extra_runtime_variables);

	// End
	$time_test_end = time();

	if(is_file($test_directory . "/pts-test-note"))
	{
		pts_add_test_note(trim(@file_get_contents($test_directory . "/pts-test-note")));
		unlink($test_directory . "pts-test-note");
	}
	if(empty($result_scale) && is_file($test_directory . "pts-results-scale"))
	{
		$result_scale = trim(@file_get_contents($test_directory . "pts-results-scale"));
		unlink($test_directory . "pts-results-scale");
	}
	if(empty($result_quantifier) && is_file($test_directory . "pts-results-quantifier"))
	{
		$result_quantifier = trim(@file_get_contents($test_directory . "pts-results-quantifier"));
		unlink($test_directory . "pts-results-quantifier");
	}
	if(empty($test_version) && is_file($test_directory . "pts-test-version"))
	{
		$test_version = @file_get_contents($test_directory . "pts-test-version");
		unlink($test_directory . "pts-test-version");
	}
	if(empty($arguments_description))
	{
		$default_test_descriptor = $xml_parser->getXMLValue(P_TEST_SUBTITLE);

		if(!empty($default_test_descriptor))
		{
			$arguments_description = $default_test_descriptor;
		}
		else if(is_file($test_directory . "pts-test-description"))
		{
			$arguments_description = @file_get_contents($test_directory . "pts-test-description");
			unlink($test_directory . "pts-test-description");
		}
		else
		{
			$arguments_description = "Phoronix Test Suite v" . PTS_VERSION;
		}
	}
	foreach(pts_env_variables() as $key => $value)
	{
		$arguments_description = str_replace("$" . $key, $value, $arguments_description);
	}
	foreach(pts_env_variables() as $key => $value)
	{
		if($key != "VIDEO_MEMORY" && $key != "NUM_CPU_CORES" && $key != "NUM_CPU_JOBS")
		{
			$extra_arguments = str_replace("$" . $key, $value, $extra_arguments);
		}
	}

	$RETURN_STRING = $test_title . ":\n";
	$RETURN_STRING .= $arguments_description . "\n";

	if(!empty($arguments_description))
	{
		$RETURN_STRING .= "\n";
	}

	// Result Calculation
	$pts_test_result->set_attribute("TEST_DESCRIPTION", $arguments_description);
	$pts_test_result->set_attribute("TEST_VERSION", $test_version);
	$pts_test_result->set_attribute("EXTRA_ARGUMENTS", $extra_arguments);
	$pts_test_result->set_result_format($result_format);
	$pts_test_result->set_result_proportion($result_proportion);
	$pts_test_result->set_result_scale($result_scale);
	$pts_test_result->set_result_quantifier($result_quantifier);
	$pts_test_result->calculate_end_result($RETURN_STRING); // Process results

	if(!empty($RETURN_STRING))
	{
		echo $this_result = pts_string_header($RETURN_STRING, "#");
		pts_text_save_buffer($this_result);
	}
	else
	{
		echo "\n\n";
	}

	pts_user_message($post_run_message);

	pts_process_remove($test_identifier);
	pts_module_process("__post_test_run", $pts_test_result);
	pts_test_refresh_install_xml($test_identifier, ($time_test_end - $time_test_start));

	return $pts_test_result;
}
function pts_global_auto_tags($extra_attr = null)
{
	// Generate automatic tags for the system, used for Phoronix Global
	$tags_array = array();

	if(!empty($extra_attr) && is_array($extra_attr))
	{
		foreach($extra_attr as $attribute)
		{
			array_push($tags_array, $attribute);
		}
	}

	switch(hw_cpu_core_count())
	{
		case 1:
			array_push($tags_array, "Single Core");
			break;
		case 2:
			array_push($tags_array, "Dual Core");
			break;
		case 4:
			array_push($tags_array, "Quad Core");
			break;
		case 8:
			array_push($tags_array, "Octal Core");
			break;
	}

	$cpu_type = hw_cpu_string();
	if(strpos($cpu_type, "Intel") !== false)
	{
		array_push($tags_array, "Intel");
	}
	else if(strpos($cpu_type, "AMD") !== false)
	{
		array_push($tags_array, "AMD");
	}
	else if(strpos($cpu_type, "VIA") !== false)
	{
		array_push($tags_array, "VIA");
	}

	$gpu_type = hw_gpu_string();
	if(strpos($cpu_type, "ATI") !== false)
	{
		array_push($tags_array, "ATI");
	}
	else if(strpos($cpu_type, "NVIDIA") !== false)
	{
		array_push($tags_array, "NVIDIA");
	}

	if(sw_os_architecture() == "x86_64" && IS_LINUX)
	{
		array_push($tags_array, "64-bit Linux");
	}

	$os = sw_os_release();
	if($os != "Unknown")
	{
		array_push($tags_array, $os);
	}

	return implode(", ", $tags_array);
}
function pts_all_combos(&$return_arr, $current_string, $options, $counter, $delimiter = " ")
{
	// In batch mode, find all possible combinations for test options
	if(count($options) <= $counter)
	{
		array_push($return_arr, trim($current_string));
	}
	else
        {
		foreach($options[$counter] as $single_option)
		{
			$new_current_string = $current_string;

			if(strlen($new_current_string) > 0)
			{
				$new_current_string .= $delimiter;
			}

			$new_current_string .= $single_option;

			pts_all_combos($return_arr, $new_current_string, $options, $counter + 1, $delimiter);
		}
	}
}
function pts_auto_process_test_option($identifier, &$option_names, &$option_values)
{
	// Some test items have options that are dynamically built
	if(count($option_names) == 1 && count($option_values) == 1)
	{
		switch($identifier)
		{
			case "auto-resolution":
				$available_video_modes = hw_gpu_xrandr_available_modes();
				$format_name = $option_names[0];
				$format_value = $option_values[0];
				$option_names = array();
				$option_values = array();

				foreach($available_video_modes as $video_mode)
				{
					$this_name = str_replace("\$VIDEO_WIDTH", $video_mode[0], $format_name);
					$this_name = str_replace("\$VIDEO_HEIGHT", $video_mode[1], $this_name);

					$this_value = str_replace("\$VIDEO_WIDTH", $video_mode[0], $format_value);
					$this_value = str_replace("\$VIDEO_HEIGHT", $video_mode[1], $this_value);

					array_push($option_names, $this_name);
					array_push($option_values, $this_value);
				}
			break;
		}
	}
}
function pts_test_options($identifier)
{
	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
	$settings_name = $xml_parser->getXMLArrayValues(P_TEST_OPTIONS_DISPLAYNAME);
	$settings_argument_prefix = $xml_parser->getXMLArrayValues(P_TEST_OPTIONS_ARGPREFIX);
	$settings_argument_postfix = $xml_parser->getXMLArrayValues(P_TEST_OPTIONS_ARGPOSTFIX);
	$settings_identifier = $xml_parser->getXMLArrayValues(P_TEST_OPTIONS_IDENTIFIER);
	$settings_menu = $xml_parser->getXMLArrayValues(P_TEST_OPTIONS_MENU_GROUP);

	$test_options = array();

	for($option_count = 0; $option_count < count($settings_name); $option_count++)
	{
		$xml_parser = new tandem_XmlReader($settings_menu[$option_count]);
		$option_names = $xml_parser->getXMLArrayValues(S_TEST_OPTIONS_MENU_GROUP_NAME);
		$option_values = $xml_parser->getXMLArrayValues(S_TEST_OPTIONS_MENU_GROUP_VALUE);
		pts_auto_process_test_option($settings_identifier[$option_count], $option_names, $option_values);

		$user_option = new pts_test_option($settings_identifier[$option_count], $settings_name[$option_count]);
		$prefix = $settings_argument_prefix[$option_count];

		$user_option->set_option_prefix($prefix);
		$user_option->set_option_postfix($settings_argument_postfix[$option_count]);

		for($i = 0; $i < count($option_names) && $i < count($option_values); $i++)
		{
			$user_option->add_option($option_names[$i], $option_values[$i]);
		}

		array_push($test_options, $user_option);
	}

	return $test_options;
}

?>
