<?
  
  class CoucheCache {

    protected $url;
    protected $cacheDB = "cache";
    protected $expiry = 0;
    protected $turbo = false;
   
    // constructor
    function __construct($url, $turbo=false, $db="cache") {
      $this->url = $url;  
      $this->expiry = 60*60*24*1000;
      $this->cacheDB = $db;
      $this->turbo = $turbo;
    }
    
    // calculate the millisecond timestamp
    private function getTS() {
      return intval(microtime(true)*1000);
    }
    
    // do a curl request to CouchDB
    private function doCurl($method, $collection, $data, $params = array()) {

      $ch = curl_init();

      if (is_array($data) && count($data)) {
        $payload = json_encode($data);
      } else {
        $payload = null;
      }

      $url = $this->url."/".$collection;

      if($method == "GET" && is_array($params) && count($params) > 0) {
        $url.="?".http_build_query($params);
      }
      
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method); /* or PUT */
      if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
      }
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-type: application/json',
          'Accept: */*'
      ));
      $response = curl_exec($ch);
      curl_close($ch);

      return json_decode($response, true);

    }
    
    // get the status of the database
    public function status() {
      return $this->doCurl("GET", $this->cacheDB, "", "");
    }
    
    // set a key/value pair
    public function set($key, $value) {
      $doc = Array();
      $doc["cacheKey"] = $key;
      $doc["value"] = $value;
      $doc["ts"] = $this->getTS() + $this->expiry;
      return $this->doCurl("POST", $this->cacheDB, $doc);
    }
    
    // get a key
    public function get($key) {
      $options = Array();
      $options["startkey"] = '["'.$key.'","z"]';
      $options["endkey"] = '["'.$key.'",'.$this->getTS().']';
      $options["limit"] = "1";
      $options["descending"] = "true";
      $options["r"] = "1";
      if($this->turbo) {
        $options["stale"] = "ok";
      }
      $retval =  $this->doCurl("GET", $this->cacheDB."/_design/fetch/_view/by_key", null, $options);
      if($retval["rows"] && $retval["rows"]["0"] && $retval["rows"]["0"]["value"]) {
        return $retval["rows"]["0"]["value"];
      } else {
        return null;
      }
    }
    
    // delete a key (set it to null)
    public function del($key) {
      return $this->set($key, null);
    }
    
    // set a zipped key
    public function zset($key, $value) {
      $zval = base64_encode(gzcompress($value));
      return $this->set($key, $zval);
    }
    
    // get a zipped key
    public function zget($key) {
      $zval = $this->get($key);
      return gzuncompress(base64_decode($zval));
    }

  }
  
/*  
  $mc = new CoucheCache("http://127.0.0.1:5984");
  $mc->set("a","1");
  echo "First value of a: ".$mc->get("a")."\n";
  $mc->del("a");
  echo "Second value of a: ".$mc->get("a")."\n";  
  $mc->zset("c","Compress me asfabfk qiwfqnwq qwoqwwnsf");
  echo "Zipped:".$mc->zget("c")."\n";
  */
?>