<?php DEFINE(INDVR, true);

#libs
include("../lib/lib.php");  #common functions

#auth check
$current_user = new DVRUser();
$current_user->CheckStatus();
$current_user->StatusAction('admin');
#/auth check

class updateDB extends DVRData{
	public $message;
	public $status;
	public $data;
	function __construct(){
		$this->message = ' ';
		$this->data = ' ';
		$mode = $_POST['mode']; unset($_POST['mode']);
		switch ($mode) {
			case 'global': $this->status = $this->Edit('global'); $this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL; break;
			case 'deleteUser' : $this->status = $this->deleteUser(); break;
			case 'updateEncoding': $this->updateEncoding(); break;
			case 'update': $this->updateField(); break;
			case 'user': $this->editUser(); break;
			case 'newUser' : $this->newUser(); break;
			case 'changeState': $this->changeState(); break;
			case 'FPS': $this->changeFPSRES('FPS'); break;
			case 'RES': $this->changeFPSRES('RES'); break;
			case 'update_control' : $this->update_control(); break;
		}
	}
	
	function update_control(){
		$id = intval($_POST['id']);
		$db = DVRDatabase::getInstance();
		$this_device = $db->DBFetchAll($db->DBQuery("SELECT * FROM Devices WHERE id='$id' "));
		$bch = bc_handle_get($this_device[0]['source_video']);
		if (isset($_POST['hue'])) { bc_set_control($bch, BC_CID_HUE, $_POST['hue']); };
		if (isset($_POST['saturation'])) { bc_set_control($bch, BC_CID_SATURATION, $_POST['saturation']); };
		if (isset($_POST['contrast'])) { bc_set_control($bch, BC_CID_CONTRAST, $_POST['contrast']); };
		if (isset($_POST['brightness'])) { bc_set_control($bch, BC_CID_BRIGHTNESS, $_POST['brightness']); };
		bc_handle_free($bch);
		$this->updateField();
	}
	
	function changeFPSRES($type){
		$id = intval($_POST['id']);
		$db = DVRDatabase::getInstance();
		$this_device = $db->DBFetchAll($db->DBQuery("SELECT * FROM Devices LEFT OUTER JOIN AvailableSources ON Devices.source_video=AvailableSources.devicepath WHERE Devices.id='$id' "));
		if ($type == 'RES'){ $res = explode('x', $_POST['value']); $res['x'] = intval($res[0]); $res['y'] = intval($res[1]); } else {
			$res['x'] = $this_device[0]['resolutionX']; $res['y'] = $this_device[0]['resolutionY']; 
		}
		$fps = ($type=='FPS') ? intval($_POST['value']) : (30/$this_device[0]['video_interval']);
		$resX = ($type=='RES') ? ($res['x']) : $this_device[0]['resolutionX'];
		
		$this_device[0]['req_fps'] = (($fps) * (($resX>=704) ? 4 : 1)) - ((30/$this_device[0]['video_interval']) * (($this_device[0]['resolutionX']>=704) ? 4 : 1));
		
		$container_card = new BCDVRCard($this_device[0]['card_id']);
		if ($this_device[0]['req_fps'] > $container_card->fps_available){
			$this->status = false;
			$this->message = ENABLE_DEVICE_NOTENOUGHCAP;
		} else {
			$this->status = ($db->DBQuery("UPDATE Devices SET video_interval='".(30/$fps)."', resolutionX='{$res['x']}', resolutionY='{$res['y']}' WHERE source_video='{$this_device[0]['source_video']}'")) ? true : false;
			$this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL;
			$container_card = new BCDVRCard($this_device[0]['card_id']);
			$this->data = $container_card->fps_available;
		}
		
	}
	
	function changeState(){
		$id = intval($_POST['id']);
		$db = DVRDatabase::getInstance();
		$this_device = $db->DBFetchAll($db->DBQuery("SELECT * FROM AvailableSources LEFT OUTER JOIN Devices ON AvailableSources.devicepath=Devices.source_video WHERE AvailableSources.id='$id' "));
		$container_card = new BCDVRCard($this_device[0]['card_id']);
		if ($this_device[0]['source_video']!=''){ //if the device is configured
		
			$this_device[0]['req_fps'] = (30/$this_device[0]['video_interval']) * (($this_device[0]['resolutionX']>=704) ? 4 : 1);
			if ($this_device[0]['disabled']){ //if it is dis
				if ($this_device[0]['req_fps'] > $container_card->fps_available){
					$this->status = false;
					$this->message = ENABLE_DEVICE_NOTENOUGHCAP;
				} else {
					$this->status = ($db->DBQuery("UPDATE Devices SET disabled='0' WHERE source_video='{$this_device[0]['source_video']}'")) ? true : false;
					$this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL;
				}
				
			} else {
				$this->status = ($db->DBQuery("UPDATE Devices SET disabled='1' WHERE source_video='{$this_device[0]['source_video']}'")) ? true : false;
				$this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL;
			}
		} else {
			$ds = ($container_card->fps_available<30) ? 1 : 0;
			if ($container_card->signal_type == 'notconfigured' || $container_card->signal_type == 'NTSC'){
				$res['y']='240';
				$enc = 'NTSC';
			} else {
				$res['y'] = '288';
				$enc = 'PAL';
			}
			$this->status = $db->DBQuery("INSERT INTO Devices (device_name, resolutionX, resolutionY, protocol, source_video, video_interval, signal_type, disabled) VALUES ('{$this_device[0]['devicepath']}', '352', '{$res['y']}', 'V4L2', '{$this_device[0]['devicepath']}', '30', '{$enc}', '$ds')") ? true : false;
			if ($ds==1) { $this->status = 'INFO'; $this->message = NEW_DEV_NEFPS; } else {
				$this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL;
			}
		}
		
	}
	function newUser(){
		if (!isset($_POST['username']) || $_POST['username']=='') { $this->status = false; $this->message = NO_USERNAME; return false; }
		if (!isset($_POST['email']) || $_POST['email']=='') { $this->status = false; $this->message = NO_EMAIL; return false; }
		if (!isset($_POST['password']) || $_POST['password']=='') { $this->status = false; $this->message = NO_PASS; return false; }
		$db = DVRDatabase::getInstance();
		$_POST['type'] = 'Users';
		$_POST['access_setup'] = ($_POST['access_setup']=='on') ? '1' : '0';
		$_POST['access_web'] = ($_POST['access_web']=='on') ? '1' : '0';
		$_POST['access_remote'] = ($_POST['access_remote']=='on') ? '1' : '0';
		$_POST['access_backup'] = ($_POST['access_backup']=='on') ? '1' : '0';
		$_POST['salt'] = genRandomString(4);
		$_POST['password'] = md5($_POST['password'].$_POST['salt']);
		$this->status = ($db->DBQuery($this->FormQueryFromPOST('insert'))) ? true : false;
		$this->message = ($this->status) ? USER_CREATED : CHANGES_FAIL;
	}
	
	function editUser(){
		if ($_POST['password']=='__default__') { unset($_POST['password']); };
		$_POST['type'] = 'Users';
		$_POST['access_setup'] = ($_POST['access_setup']=='on') ? '1' : '0';
		$_POST['access_web'] = ($_POST['access_web']=='on') ? '1' : '0';
		$_POST['access_remote'] = ($_POST['access_remote']=='on') ? '1' : '0';
		$_POST['access_backup'] = ($_POST['access_backup']=='on') ? '1' : '0';
		if (!isset($_POST['username']) || $_POST['username']=='') { $this->status = false; $this->message = NO_USERNAME; return false; }
		if (!isset($_POST['email']) || $_POST['email']=='') { $this->status = false; $this->message = NO_EMAIL; return false; }
		if ($_SESSION['id']==$_POST['id'] && $_POST['access_setup']==0) { $this->message = CANT_REMOVE_ADMIN; return false; }
		$db = DVRDatabase::getInstance();
		$this->status = ($db->DBQuery($this->FormQueryFromPOST('update'))) ? true : false;
		$this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL;
	}
	
	function deleteUser(){
		$id = intval($_POST['id']);
		if ($_SESSION['id']==$id) { $this->message = DELETE_USER_SELF; return false; }
		$db = DVRDatabase::getInstance();
		$this->status = ($db->DBQuery("DELETE FROM Users WHERE id='$id'")) ? true : false;
		$this->message = ($this->status) ? USER_DELETED : CHANGES_FAIL;
		return true;
	}
	
	function updateEncoding(){
		$db = DVRDatabase::getInstance();
		$card_id = intval($_POST['id']);
		$signal_type = $db->DBEscapeString($_POST['signal_type']);
		if ($signal_type=='NTSC'){ $resolution_full = 480; $resolution_quarter = 240; } else { $resolution_full = 576; $resolution_quarter = 288; };
		
		$this->status = $db->DBQuery("UPDATE Devices SET signal_type='{$signal_type}' WHERE source_video IN (SELECT devicepath FROM AvailableSources WHERE card_id={$card_id})");
		$this->status = $db->DBQuery("UPDATE Devices SET resolutionY=$resolution_full WHERE source_video IN (SELECT devicepath FROM AvailableSources WHERE card_id={$card_id}) AND resolutionY>300");
		$this->status = $db->DBQuery("UPDATE Devices SET resolutionY=$resolution_quarter WHERE source_video IN (SELECT devicepath FROM AvailableSources WHERE card_id={$card_id}) AND resolutionY<300");
		$this->message = ($this->status) ? DEVICE_ENCODING_UPDATED : DB_FAIL_TRY_LATER;
	}
	
	function updateField(){
		$db = DVRDatabase::getInstance();
		$this->status = $db->DBQuery($this->FormQueryFromPOST('update'));
		$this->message = ($this->status) ? CHANGES_OK : CHANGES_FAIL;
	}
	
	function outputXML(){
		switch ($this->status){
			case true: $s = 'OK';    break;
			case false: $s = 'F';    break;
			case 'INFO': $s= 'INFO'; break;
		}
		header('Content-type: text/xml');
		echo "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
				<response>
					<status>{$s}</status>
					<msg>{$this->message}</msg>
					<data>{$this->data}</data>
				</response>";
				
	}

}

$update = new updateDB;
$update->outputXML();
?>