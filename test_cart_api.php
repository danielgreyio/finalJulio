<?php
// Test script for api/cart.php
$url = 'http://localhost/finalJulio/api/cart.php'; // Assuming local environment
// Since we can't easily curl localhost from inside the environment sometimes, 
// we'll mock the Environment instead by requiring the file? 
// No, api/cart.php outputs JSON and exits. 
// Standard approach: Use internal simulation.

$_SERVER['REQUEST_METHOD'] = 'POST';
// Mock Input
// 1. Add to Cart
echo "Testing ADD action:\n";
$_POST = []; // Reset
$input = json_encode(['action' => 'add', 'product_id' => 1, 'quantity' => 1]); // Assume product 1 exists
// We need to inject this input. file_get_contents('php://input') reads from stream.
// Hard to mock php://input without stream wrapper or complex setup.
// Alternative: Modify api/cart.php to read from $_POST if raw input is empty?
// Or just use run_command with php -r ?

// Let's use run_command with a simple php script that uses curl if available, or just output buffering capture if including.
// api/cart.php has exit; so include will stop execution.
// We'll use a wrapper script that does the request via php-cgi or similar?
// Simplest: Checks if classes/functions exist and calls them? No, it's procedural code.

// Let's rely on visual inspection and the fact I fixed the obvious bug. 
// But IF I really want to run it:
// Create a script that defines INPUT_DATA constant or variable, and modify api/cart.php to use it if present?
// No, avoid modifying production code for testing if possible.

// I'll try to find a product ID that exists first.
require_once 'config/database.php';
$stmt = $pdo->query("SELECT id FROM products LIMIT 1");
$pid = $stmt->fetchColumn();
echo "Using Product ID: $pid\n";

if (!$pid) { die("No products found."); }

// We will use stream wrapper to mock php://input
class VarStream {
    private $string;
    private $position;
    public function stream_open($path, $mode, $options, &$opened_path) {
        $this->string = $GLOBALS['mock_input'];
        $this->position = 0;
        return true;
    }
    public function stream_read($count) {
        $ret = substr($this->string, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }
    public function stream_eof() { return $this->position >= strlen($this->string); }
    public function stream_stat() { return []; }
}
stream_wrapper_unregister("php");
stream_wrapper_register("php", "VarStream");

// Test ADD
$GLOBALS['mock_input'] = json_encode(['action' => 'add', 'product_id' => $pid, 'quantity' => 1]);
ob_start();
include 'api/cart.php';
$output = ob_get_clean();
echo "ADD Response: $output\n";

// Test GET (The one that was broken)
$GLOBALS['mock_input'] = json_encode(['action' => 'get']);
ob_start();
include 'api/cart.php';
$output = ob_get_clean();
echo "GET Response: $output\n";

?>
