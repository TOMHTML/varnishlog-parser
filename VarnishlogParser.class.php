<?php

namespace VarnishlogParser;

require_once("Transaction.class.php");

/**
 * Handler class to parse an play with varnishlog
 */
class VarnishlogParser {
  /*
   Store Varnish transactions,
   keys are vxid
   values are DefaultTransaction objects
   */
  public static $list_vxids = array();

  /*
   Private counter, avoid output a transaction twice.
   */
  private static $outputed_transactions = array();

  /**
   * Parse a Varnishlog file and return
   * an array with all transactions (objects).
   * @throws Exception        When file error.
   *
   * @param  String $filepath Path to varnishlog file.
   * @return Array            List of transactions/requests.
   */
  public static function parse($filepath){
    if(!file_exists($filepath))
      throw new \Exception("File $filepath does not exist.");
    if(!is_readable($filepath))
      throw new \Exception("File $filepath is not readable.");
    $handle = @fopen($filepath , "r");
    if(!$handle)
      throw new \Exception("Error while reading $filepath.");
    while ($line = fscanf($handle, "%d\t%s\t%s %[^\n]\n")) {
        $vxid = $tag = $direction = $payload = null;
        // vxid : transaction number
        // tag : RespProtocol, ReqUrl, VCL_call... see full list at
        //          https://www.varnish-cache.org/docs/4.1/reference/vsl.html
        // direction : b = backend, c = client
        // payload : information about tag
        list ($vxid, $tag, $direction, $payload) = $line;
        if(empty(self::$list_vxids[$vxid])){
          // New transaction
          self::$list_vxids[$vxid] = self::factory($vxid, $tag, $direction, $payload);
          continue;
        }
        self::$list_vxids[$vxid]->addInformation($tag, $payload);
    }
    fclose($handle);
    self::cleanTransactions();
    return self::$list_vxids;
  }

  /**
   * Create a new transaction, with
   * specific class.
   *
   * @param  Integer $vxid       Varnish transaction ID.
   * @param  String $tag      VSL tag.
   * @param  String $direction   'c', 'b' or '-'.
   * @param  String $payload     Information provided with tag.
   * @return DefaultTransaction  New request or transaction.
   */
  private static function factory($vxid, $tag, $direction='', $payload=''){
    // Every Vxid transaction should begin with "Begin" tag...
    if($tag == "Begin"){
      list($type, $parent, $reason) = explode(' ', $payload);
      /*
        %s %d %s
        |  |  |
        |  |  +- Reason
        |  +---- Parent vxid
        +------- Type ("sess", "req" or "bereq")
       */
      if($type == "req")
        return new ClientRequest($vxid,$direction,$payload);
      if($type == "bereq")
        return new BackendRequest($vxid,$direction,$payload);
    }
    return new DefaultTransaction($vxid,$direction,$payload);
  }

  /**
   * Delete useless transactions, such as health checks.
   * Only applies on self::$list_vxids.
   *
   * @return void
   */
  private static function cleanTransactions(){
    foreach(self::$list_vxids as $vxid => $transaction){
      // Pings, healthchecks, ...
      if($transaction->direction == '-')
        unset(self::$list_vxids[$vxid]);
      // Uncomplete transactions
      if(is_null($transaction->parent))
        unset(self::$list_vxids[$vxid]);
    }
  }

  /**
   * Simple output in order to graph sequences
   * with www.websequencediagrams.com.
   *
   * Example :
   *   participant Client
   *   participant Varnish
   *   participant Cache
   *   participant Backend
   *   note over Client: XID 350442\n192.168.0.1
   *   Client->Varnish: GET /tom?test=1 (350443)
   *   Varnish->Cache: GET /tom
   *   Cache->Varnish: HIT
   *   Varnish->Client: 200 OK (350443)
   *
   * @param  Array  $transactions  Requests list.
   * @param  Boolean $return       Print if FALSE, like print_r().
   * @return String                Exchange information, or void.
   */
  public static function simpleAnalysis($transactions, $return=FALSE){
    $ret = '';
    $ret .= "participant Client\n";
    $ret .= "participant Varnish\n";
    $ret .= "participant Cache\n";
    $ret .= "participant Backend\n";
    $ret .= "\n";
    self::$outputed_transactions = array();
    foreach ($transactions as $transaction) {
      if(in_array($transaction->vxid, self::$outputed_transactions))
        continue;
      self::$outputed_transactions[] = $transaction->vxid;
      $ret .= self::toStringTransaction($transaction->vxid, $transactions);
    }
    if($return)
      return $ret;
    print $ret."\n";
  }

  /**
   * Output transaction as multiple lines
   * (one par exchange).
   *
   * @param  Integer $vxid         Transaction ID.
   * @param  Array   $transactions Requests list.
   * @return String                Simple transaction output.
   */
  private static function toStringTransaction($vxid, $transactions){
    $ret = '';
    if(empty($transactions[$vxid]))
      return $ret; // Incomplete transaction ?
    $transaction = $transactions[$vxid];
    $name = $transaction->getName();
    $ret .= $transaction->toStringRequest($name)."\n";
    foreach ($transaction->children as $child_vxid => $reason) {
      $ret .= self::toStringTransaction($child_vxid,$transactions);
    }
    $ret .= $transaction->toStringResponse($name)."\n";
    self::$outputed_transactions[] = $vxid;
    return $ret;
  }

  /**
   * Send simple transaction output to websequencediagrams.com
   * and return link to image (sequence diagram).
   *
   * Please, do not abuse this usefull service!
   * This image must be retrieved within 2 minutes.
   *
   * @param  String $transactions_string Simple transaction output.
   * @return String                      URL, or FALSE if error.
   */
  public static function getSequenceDiagram($transactions_string) {
    $args = array(
      "apiVersion" => "1",
      "message" => $transactions_string,
      "style" => "rose",
      "format" => "png",
      // "apikey" => "OPTIONAL API KEY HERE"
    );
    $params = array();
    foreach ($args as $key => $value ) {
        $params[] = urlencode($key) . "=" . urlencode($value);
    }

    $ch = curl_init("http://www.websequencediagrams.com");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($httpcode != 200)
      return FALSE;
    $json = @json_decode($response);
    if(!is_object($json) || !isset($json->img))
      return FALSE;
    return "http://www.websequencediagrams.com/" . $json->img;
  }
}
