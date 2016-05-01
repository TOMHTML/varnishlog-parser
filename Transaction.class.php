<?php

namespace VarnishlogParser;

/**
 * Basis for every transaction in Varnish.
 * Most useful when overrided...
 */
class DefaultTransaction {
  public $vxid; // Transaction number
  public $direction; // 'b', 'c' or '-'
  public $children; // Transactions emitted by this one.
  public $parent; // Vxid of parent
  public $creation_reason; // Why this transaction has been created

  /**
   * Default constructor
   * @param Integer $vxid            Transaction ID.
   * @param String $direction        "c", "b" or "-"
   * @param String $creation_payload Reason why it has been created.
   */
  function __construct($vxid, $direction, $creation_payload){
    $this->vxid = $vxid;
    $this->direction = $direction;
    $type = $parent = $reason = null;
    @list($type, $parent, $reason) = explode(' ', $creation_payload);
    $this->parent = $parent;
    $this->creation_reason = $reason;
    $this->children = array();
  }

  /**
   * Add a new child transaction.
   * @param Integer $vxid   Transaction ID.
   * @param String  $tag    Creation reason.
   */
  function addChild($vxid, $tag){
    $this->children[$vxid] = $tag;
  }

  /**
   * add new information about transaction.
   * @param Integer $vxid   Transaction ID.
   * @param String $payload Data provided.
   */
  function addInformation($tag, $payload){
    switch ($tag) {
      case 'Link':
        list($type, $vxid_dest, $tag_dest) = explode(' ',$payload);
        $this->addChild($vxid_dest,$tag_dest);
        break;
      default:
        $this->{$tag}[] = $payload;
        break;
    }
  }

  /**
   * Simple representation of this transaction.
   * @param  String $parent_name Name for initiator.
   * @return String              Simple representation.
   */
  function toString($parent_name=null){
    return $this->toStringRequest($parent_name)."\n".$this->toStringResponse($parent_name);
  }

  /**
   * Simple representation of input data.
   * If it's a user session, output client IP.
   * @param  String $parent_name Name for initiator.
   * @return String              Simple representation.
   */
  function toStringRequest($parent_name=null){
    if(isset($this->SessOpen[0])){
      list($remote_ip) = sscanf($this->SessOpen[0],'%s %d %s %s %s %d');
      /*
      %s %d %s %s %s %d
      |  |  |  |  |  |
      |  |  |  |  |  +- File descriptor number
      |  |  |  |  +---- Local TCP port ('-' if !$log_local_addr)
      |  |  |  +------- Local IPv4/6 address ('-' if !$log_local_addr)
      |  |  +---------- Listen socket (-a argument)
      |  +------------- Remote TCP port
      +---------------- Remote IPv4/6 address
       */
    }
    return "note over Client: vxid ".$this->vxid."\\n".$remote_ip;
  }

  /**
   * Simple representation of output data.
   * @param  String $parent_name Name for initiator.
   * @return String              Simple representation.
   */
  function toStringResponse($parent_name=null){

  }

  /**
   * Get a simple name for this transaction.
   * @return String  The name of the transaction.
   */
  function getName(){
    return $this->vxid;
  }
}



/**
 * A request is an HTTP exchange between two entities.
 */
class Request extends DefaultTransaction {
  public $query; // Original input data from parent or user
  public $response; // Final response data from backend or varnish
  public $cache; // Manipulated data to lookup cache (Client) or store into cache (Backend)
  public $other; // Other headers

  public $vcl_calls; // list of VCL calls and their return values.
  private $open_vcl_subs; // Temporary store current VCL call.

  /**
   * @see  parent
   */
  function __construct($vxid, $direction, $creation_payload){
    parent::__construct($vxid, $direction,$creation_payload);
    $this->query = array('headers'=>array());
    $this->response = array('headers'=>array());
    $this->cache = array('headers'=>array());
    $this->other = array();
    $this->vcl_calls = array();
    $this->open_vcl_subs = array();
  }

  /**
   * Add new information about current transaction.
   * Any information is categorized by tag type.
   * Many tags are currently ignored...
   *
   * @param String $tag  A varnishlog tag.
   * @param String $payload Information provided.
   */
  function addInformation($tag, $payload){
    switch ($tag) {
      /** SPECIFIC */
      case 'Link':
        // Creation of a child transaction
        list($type, $vxid_dest, $tag_dest) = explode(' ',$payload);
        $this->addChild($vxid_dest,$tag_dest);
        return;
        break;
      case 'VCL_call':
        array_push($this->open_vcl_subs, $payload);
        return;
        break;
      case 'VCL_return':
        $last_vcl_call = array_pop($this->open_vcl_subs);
        if(is_null($last_vcl_call)){
          $last_vcl_call = "RECV";
        }
        $this->vcl_calls[$last_vcl_call] = $payload;
        return;
        break;
      case 'CLI':
      case 'SessOpen':
      case 'SessClose':
        $this->other[$tag][] = $payload;
        return;
        break;
      case 'VCL_Log':
        $this->other[$tag][] = $payload;
        return;
        break;
      /** HEADERS */
      case 'ReqHeader':
      case 'BereqHeader':
      case 'RespHeader':
      case 'BerespHeader':
      case 'ObjHeader':
        $this->addHeader($tag,$payload);
        return;
        break;
      case 'ReqUnset':
      case 'BereqUnset':
      case 'RespUnset':
      case 'BerespUnset':
        $this->removeHeader($tag,$payload);
        return;
        break;
      /** MAIN HEADERS */
      case 'ReqStart':
      case 'ReqMethod':
      case 'ReqURL':
      case 'ReqProtocol':
      case 'BereqMethod':
      case 'BereqURL':
      case 'BereqProtocol':
        $data_type = "query";
        $tag = preg_replace('/^(Bereq|Req)/','',$tag);
        break;
      case 'RespProtocol':
      case 'RespStatus':
      case 'RespReason':
      case 'BerespProtocol':
      case 'BerespStatus':
      case 'BerespReason':
        $data_type = "response";
        $tag = preg_replace('/^(Beresp|Resp)/','',$tag);
        break;
      case 'ObjProtocol':
      case 'ObjStatus':
      case 'ObjReason':
      case 'TTL':
        $data_type = "cache";
        $tag = preg_replace('/^Obj/','',$tag);
        break;
      /** TAG IGNORED */
      case 'Timestamp':
      case 'Begin':
      case 'End':
      case 'Backend':
      case 'BackendClose':
      case 'BackendOpen':
      case 'BackendReuse':
      case 'BereqAcct':
      case 'Debug':
      case 'Fetch_Body':
      case 'Gzip':
      case 'Hit':
      case 'HitPass':
      case 'Length':
      case 'ReqAcct':
      case 'Storage':
      case 'VCL_acl':
        // Ignored tag, for now...
        return;
        break;
      default:
        trigger_error("Unknown tag '".$tag."'.", E_USER_WARNING);
        // Please, complete previous list!
        return;
        break;
    }
    if(!isset($this->{$data_type}[$tag]))
      $this->{$data_type}[$tag] = $payload;
    if($data_type != "cache")
      $this->cache[$tag] = $payload;
  }

  /**
   * Store a header, avoiding to erase it
   * because first entry is from real query/response
   * then any override is for cache (query or store).
   *
   * @param String $tag      Tag name.
   * @param String $payload  "Header: value".
   */
  function addHeader($tag, $payload){
    $header_name = $header_value = null;
    @list($header_name, $header_value) = sscanf($payload,'%[^:]: %[^[]]');
    /*
      %s: %s
      |   |
      |   +- Header value
      +----- Header name
     */
    if(!$header_name)
      return;
    $write_to_cache = true;
    $write_to_query = false;
    $write_to_response = false;
    switch ($tag) {
      case 'ReqHeader':
        $write_to_query = true;
        if(!empty($this->open_vcl_subs))
          $write_to_query = false; // because manipulated in VCL_recv
        break;
      case 'RespHeader':
        $write_to_response = true;
        if(!empty($this->open_vcl_subs))
          $write_to_cache = false; // because manipulated in VCL_deliver
        break;
      case 'BereqHeader':
        $write_to_query = true;
        if(!empty($this->open_vcl_subs))
          $write_to_cache = false; // because manipulated in VCL_fetch
        break;
      case 'BerespHeader':
        $write_to_response = true;
        if(!empty($this->open_vcl_subs))
          $write_to_cache = false; // because manipulated in VCL_backend_response
        break;
      case 'ObjHeader':
      default:
        $write_to_cache = true;
        break;
    }
    if($write_to_query && !isset($this->query["headers"][$header_name]))
      $this->query["headers"][$header_name] = $header_value;
    if($write_to_response && !isset($this->response["headers"][$header_name]))
      $this->response["headers"][$header_name] = $header_value;
    if($write_to_cache)
      $this->cache["headers"][$header_name] = $header_value;
  }

  /**
   * Remove header, usually in VCL_recv
   * or VCL_backend_response.
   *
   * @param String $tag      Tag name.
   * @param  String $payload  "Header: value"
   */
  function removeHeader($tag, $payload){
    $header_name = $header_value = null;
    @list($header_name, $header_value) = sscanf($payload,'%[^:]: %[^[]]');
    if(!$header_name)
      return;
    $write_to_cache = true;
    $write_to_query = false;
    $write_to_response = false;
    switch ($tag) {
      case 'ReqUnset':
        $write_to_query = false; // original request cannot remove headers
        $write_to_cache = true;
        break;
      case 'RespUnset':
        $write_to_response = true;
        if(!empty($this->open_vcl_subs))
          $write_to_cache = false; // because cache already queried
        break;
      case 'BereqUnset':
        $write_to_query = true;
        $write_to_cache = true; // because manipulated in VCL_fetch
        break;
      case 'BerespUnset':
        $write_to_response = false; // original backend response cannot remove headers
        $write_to_cache = true;
        break;
      default:
        $write_to_cache = true;
        break;
    }
    if($write_to_query)
      unset($this->query["headers"][$header_name]);
    if($write_to_response)
      unset($this->response["headers"][$header_name]);
    if($write_to_cache)
      unset($this->cache["headers"][$header_name]);
  }
}


/**
 * A client request is a request emited by
 * a user or an ESI tag in HTML page.
 */
class ClientRequest extends Request {
  /**
   * Did Varnish looked up in its cache?
   * @return Boolean
   */
  private function isCacheQueried(){
    return isset($this->vcl_calls['RECV']) && $this->vcl_calls['RECV'] == "hash";
  }

  /**
   * What was the cache response?
   * @return Boolean "HIT" or "PASS"
   * @todo  handle "Hit For Pass" objects!
   */
  private function getCacheResponse(){
    if(isset($this->vcl_calls['HIT']))
      return "HIT";
    if(isset($this->vcl_calls['PASS']))
      return "PASS";
    return "???";
  }

  /**
   * Request and subrequests representation
   * as string.
   * @see parent.
   */
  function toStringRequest($parent_name=null){
    $ret = '';
    if(is_null($parent_name))
      $parent_name = $this->parent;
    $ret .= $parent_name."->Varnish:".$this->query['Method']." ".$this->query['URL']." (".$this->vxid.")";
    if($this->isCacheQueried()){
      $ret .= "\n";
      $ret .= "Varnish->Cache:".$this->cache['Method']." ".$this->cache['URL'];
      $ret .= "\n";
      $ret .= "Cache->Varnish:".$this->getCacheResponse();
      $ret .= "\n";
    }
    return $ret;
  }

  /**
   * Request and subrequests responses
   * representation as string.
   * @see parent.
   */
  function toStringResponse($parent_name=null){
    if(is_null($parent_name))
      $parent_name = $this->parent;
    return "Varnish->".$parent_name.":".$this->cache['Status']." ".$this->cache['Reason']." (".$this->vxid.")";
  }

  /**
   * Get simple name for this transaction.
   * @return String
   */
  function getName(){
    if($this->creation_reason == "restart")
      return "Varnish";
    if($this->creation_reason == "esi")
      return "Varnish";
    return "Client";
  }
}


/**
 * A backend request is a request emited
 * by Varnish to a backend.
 * @todo  handle multiple backends.
 */
class BackendRequest extends Request {
  /**
   * @see  parent
   */
  function toStringRequest($parent_name=null){
    return "Varnish->Backend:".$this->query['Method']." ".$this->query['URL']." (".$this->vxid.")";
  }

  /**
   * @see  parent
   */
  function toStringResponse($parent_name=null){
    return "Backend->Varnish:".$this->response['Status']." ".$this->response['Reason']." (".$this->vxid.")";
  }

  /**
   * @see  parent
   */
  function getName(){
    return "Varnish";
  }

}
