<?php DEFINE(INDVR, true);

#lib
include("../lib/lib.php");  #common functions


#auth check
$current_user = new DVRUser();
$current_user->CheckStatus();
$current_user->StatusAction('viewer');
#/auth check
	
	Class LocalMonitor extends DVRData{
	public $data;
	
	public function __construct(){
		$id = intval($_GET['id']);
		$this->data = $this->GetObjectData('Devices', 'id', $id);
	}
}

$monitor = new LocalMonitor;

$multi = ($_GET['multi']) ? true : false;
$bch = false;
$boundary = "MJPEGBOUNDARY";
$dev = $monitor->data[0]['source_video'];

header("Cache-Control: no-cache");
header("Cache-Control: private");
header("Pragma: no-cache");

session_write_close();

$bch = bc_handle_get("$dev");
if ($bch == false) {
	print "Failed to open $dev";
	exit;
}

if (bc_set_mjpeg($bch) == false) {
	print "Failed to set MJPEG on $dev";
	exit;
}

if (bc_handle_start($bch) == false) {
	print "Faled to start $dev";
	exit;
}

if (isset($_GET['multipart'])) {
        $multi = true;
        header("Content-type: multipart/x-mixed-replace; boundary=$boundary");
        print "\r\n--$boundary\r\n";
} else {
	header("Content-type: image/jpeg");
}

function print_image() {
	global $multi, $bch, $boundary;

	if (bc_buf_get($bch) == false)
		exit;

	if ($multi) {
		print "Content-type: image/jpeg\r\n";
		print "Content-size: " . bc_buf_size($bch) . "\r\n\r\n";
	}

	print bc_buf_data($bch);

	if ($multi)
		print "\r\n--$boundary\r\n";
}

# For multi, let's do some weird stuff
if ($multi) {
	set_time_limit(0);
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
	for ($i = 0; $i < ob_get_level(); $i++)
		ob_end_flush();
	ob_implicit_flush(1);
}

do {
	print_image();
} while($multi);

bc_handle_free($bch);
?>
