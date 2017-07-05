<?php
/*
Copyright (c) 2016 1WAY HOSTING (https://1way.pro)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
//    ___             __ _
//   / __\___  _ __  / _(_) __ _
//  / /  / _ \| '_ \| |_| |/ _` |
// / /__| (_) | | | |  _| | (_| |
// \____/\___/|_| |_|_| |_|\__, |
//                         |___/

if (!defined("WHMCS"))
    die("This file cannot be accessed directly  _|_");

//I have changed paths in those classes in order to make'em work. I don't like this too...
define(MODDIR, $_SERVER['DOCUMENT_ROOT'] . "/modules/servers/scaleway/");
include(MODDIR . 'lib/phpseclib104/Net/SSH2.php');
include(MODDIR . 'lib/phpseclib104/Crypt/RSA.php');

//     _    ____ ___     ____    _    _     _     ____
//    / \  |  _ \_ _|   / ___|  / \  | |   | |   / ___|
//   / _ \ | |_) | |   | |     / _ \ | |   | |   \___ \
//  / ___ \|  __/| |   | |___ / ___ \| |___| |___ ___) |
// /_/   \_\_|  |___|   \____/_/   \_\_____|_____|____/
class ScalewayApi
{
	private $token = "";
    private $callUrl = "";

	// Status codes returned by scaleway
	public $statusCodes =
                [
                    "200" => "Scaleway API - OK",
                    "400" => "Scaleway API - Error 400: bad request. Missing or invalid parameter?",
                    "201" => "Scaleway API - Error 201: This is not an error but you should not be here!",
                    "204" => "Scaleway API - Error 204: A delete action performed successfully! You should not be here however!",
                    "401" => "Scaleway API - Error 401: auth error. No valid API key provided!",
                    "402" => "Scaleway API - Error 402: request failed. Parameters were valid but request failed!",
                    "403" => "Scaleway API - Error 403: forbidden. Insufficient privileges to access requested resource or the caller IP may be blacklisted!",
                    "404" => "Scaleway API - Error 404: not found 404 not found 404 not found 404 not found, what are you looking for?",
                    "50x" => "Scaleway API - Error 50x: means server error. Dude, this is bad..:(",

                    //Custom
                    "123" => "Error 123: means new volume creation failed. This error appear when try to allocate new volume for the new server!"
                ];
	
	public static $commercialTypes =
                [
                    // Type => processor_cores D[dedicated]/S[hared]C_RAM
                    "C1"        => "ARM_4DC_2GB",
                    "C2S"       => "x86_4DC_8GB",
                    "C2M"       => "x86_8DC_16GB",
                    "C2L"       => "x86_8DC_32GB",

                    //X64
                    "X64-2GB"      => "x64_6SC_2GB",
                    "X64-4GB"      => "x64_6SC_4GB",
                    "X64-8GB"      => "x64_6SC_8GB",
                    "X64-15GB"      => "x64_6SC_15GB",
                    "X64-30GB"      => "x64_8SC_30GB",
                    "X64-60GB"      => "x64_10SC_60GB",
                    "X64-120GB"     => "x64_12SC_120GB",

                    //ARMs
                    "ARM64-2GB"     => "ARM_4SC_2GB",
                    "ARM64-4GB"     => "ARM_6SC_4GB",
                    "ARM64-8GB"     => "ARM_8SC_8GB",

                    //PS: Strange, they say "8 Dedicated x86 64bit", x86 means 32bit...;
                ];

    public static $availableLocations =
                [
                    "Paris"     => "par1",
                    "Amsterdam" => "ams1",

                    //Let's accept par1 and ams1 as valid locations
                    "par1"      => "par1",
                    "ams1"      => "ams1",
                ];

	function __construct($tokenStr, $location)
	{
		$this->token = $tokenStr;

        //We have to build call url with the right location (par1 or ams1)
        //Example: https://cp-par1.scaleway.com
        $this->callUrl = "https://cp-" . ScalewayApi::$availableLocations[$location] . ".scaleway.com";
	}
	// ____       _            _            
	//|  _ \ _ __(_)_   ____ _| |_ ___  ___ 
	//| |_) | '__| \ \ / / _` | __/ _ \/ __|
	//|  __/| |  | |\ V / (_| | ||  __/\__ \
	//|_|   |_|  |_| \_/ \__,_|\__\___||___/
	//
	//This is function used to call Scaleway API
	private function call_scaleway_api($token, $http_method, $endpoint, $get = array(), $post = array())
	{
		if ( !empty($get) ) 
			$endpoint .= '?' . http_build_query($get);
	 
		$call = curl_init();
		
		if($endpoint == "/organizations")
			curl_setopt($call, CURLOPT_URL, 'https://account.scaleway.com' . $endpoint);
		else
			curl_setopt($call, CURLOPT_URL, $this->callUrl . $endpoint);
		
		curl_setopt($call, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		
		$headers = [
					"X-Auth-Token: " . $token,	
					"Content-Type: application/json"
				   ];
		curl_setopt($call, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($call, CURLOPT_RETURNTRANSFER, true);	
	 
		if ($http_method == 'POST') 
        {
			curl_setopt($call, CURLOPT_POST, true);
			curl_setopt($call, CURLOPT_POSTFIELDS, json_encode($post));
		}
		else
		{
			curl_setopt($call, CURLOPT_POST, true);
			curl_setopt($call, CURLOPT_CUSTOMREQUEST, $http_method);
			curl_setopt($call, CURLOPT_POSTFIELDS, http_build_query($post));
		}
		
		$result = curl_exec($call);
		$resultHttpCode = curl_getinfo($call, CURLINFO_HTTP_CODE);
		curl_close($call);

		if($resultHttpCode == "")
        {
            $tmpArr = array("message" => "Two possibilities: 1. CURL request to Scaleway failed; you may be behind a firewall! Try this to be sure check with: ping cp-par1.scaleway.com<br>2. Location passed to API is invalid!");
            $result = json_encode($tmpArr);
        }

		//Return an arry with HTTP_CODE returned and the JSON content writen by server.
		return array(
					"httpCode" => $resultHttpCode,
					"json" => $result
					);
	}
	
	//Function to get ony main organization id as we need it for new created server
	private function getMainOrganizationId()
	{
		$organizationsResult = $this->retrieve_organizations();
		$orgReturnCode = $organizationsResult['httpCode'];
		$orgJsonCode = $organizationsResult['json'];

		if($orgReturnCode == 200)
		{
			$organizationsArray = json_decode($orgJsonCode, true);
			for($i=0; $i < count($organizationsArray['organizations']); $i++)
			{	
				$organization = $organizationsArray['organizations'][$i];
				
				$org_id = $organization['id'];		
				//We need only first organization ID as there can't be created multiple organizations on a single account.
				return $org_id;
				break; //Just testing to see if I get warnings, not my paranoia :)
			}
		}
		else
		{
			echo $this->statusCodes[$orgReturnCode];
		}
	}

    //Server actions are: power on, power off, reboot
    private function execute_server_action($action, $server_id)
    {
        if($action != "poweron" && $action != "poweroff" && $action != "reboot" && $action != "terminate")
        {
            $resp =
                [
                    "httpCode" => "400",
                    "json" => "{\"error\" : \"error\"}"
                ];
            return $resp;
        }

        $http_method = "POST";
        $endpoint = "/servers/" . $server_id . "/action";
        $postParams =
            [
                "action" => $action
            ];

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), $postParams);
        return $result;
    }

    // ____        _     _ _
	//|  _ \ _   _| |__ | (_) ___ ___
	//| |_) | | | | '_ \| | |/ __/ __|
	//|  __/| |_| | |_) | | | (__\__ \
	//|_|    \__,_|_.__/|_|_|\___|___/
	//
	//This function will return an array() with all instanced servers for the $token given
	public function retrieve_servers_list()
	{		
		$http_method = "GET";
		$endpoint = "/servers";
		
		$result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());	
		
		return $result;
	}
	
	//This function return all organizations in JSON format
    public function retrieve_organizations()
	{		
		$http_method = "GET";
		$endpoint = "/organizations";
		
		$result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());	
		
		return $result;
	}

    //Function to get all images from ImageHub and created by user
	public function retrieve_images()
	{
		$http_method = "GET";
		$endpoint = "/images";
		
		$result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
		
		return $result;
	}

    // Get all avilable volumes created by API owner
    public function retrieve_volumes()
    {
        $http_method = "GET";
        $endpoint = "/volumes";

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());

        return $result;
    }

    //Create a new volume in order to be able to create a new server
    public function create_new_volume($name, $size)
    {
        $http_method = "POST";
        $endpoint = "/volumes";
        $organization = $this->getMainOrganizationId();
        $volumeType = "l_ssd";

        $postParams =
            [
                "name" => $name,
                "organization" => $organization,
                "volume_type" => $volumeType,
                "size" => $size

            ];

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), $postParams);

        return $result;
    }

    //Get volume info by ID
    public function retrieve_volume_info($id)
    {
        $http_method = "GET";
        $endpoint = "/volumes/" . $id;

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());

        return $result;
    }

    //Delete a volume by it's id
    public function delete_volume($id)
    {
        $http_method = "DELETE";
        $endpoint = "/volumes/" . $id;

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());

        return $result;
    }

	//Function to instantiate a new server
	public function create_new_server($name, $image, $commercial_type, $tags)
	{
		$http_method = "POST";
		$endpoint = "/servers";
		$organization = $this->getMainOrganizationId();

        /*
        //We create only one volume for now
        $volume = $this->create_new_volume("vol1_" . $name, 50000000000);
        if($volume['httpCode'] != 201)
        {
            //Can't create new volume so return the error encounted!
            return $volume;
        }
        $volArry= json_decode($volume['json'], true);
        $vol_id = $volArry['volume']['id'];

        //By default, a volume is attached to our server. Use this to add more than one
        $volumes =
            [
                "1" =>
                    [
                    "name" => "vol1_" . $name,
                    //"organization" => $this->getMainOrganizationId()
                    //"size" => 50,
                    //"volume_type" => "l_ssd"
                    "id" => $vol_id
                    ]
            ];
        */
        $postParams =
            [
                "organization" => $organization,
                "name"         => $name,
                "image"        => $image,
                "commercial_type" => $commercial_type,
                "tags"         => $tags,
                "enable_ipv6"  => false
                //"volumes"      => $volumes
            ];

		$server_creation_result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), $postParams);

        if($server_creation_result['httpCode'] != 210)
        {
            //If created more than one volumes consider removing it as server creation failed
            if(isset($vol_id))
                $this->delete_volume($vol_id);
        }

        //Dirty code, sorry.
        $srv_id = json_decode($server_creation_result["json"], true)["server"]["id"];
        $this->execute_server_action("poweron", $srv_id);

        return $server_creation_result;
	}

    //Function which return server info
    public function retrieve_server_info($server_id)
    {
        if($server_id == "") //We have to prevent endpoint becaming /servers/{NULL}, it will print all servers and we don't want this!
            $server_id = "7b6d2181-0000-0000-0000-3ebd066076f1";

        $http_method = "GET";
        $endpoint = "/servers/" . $server_id;

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;
    }

    //Delete an IPv4 Address
    public function delete_ip_address($ip_id)
    {
        $http_method = "DELETE";
        $endpoint = "/ips/" . $ip_id;

        $result = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());
        return $result;

    }

    //Delete a server by ID. This include IP and Volumes removal
    public function server_terminate($server_id)
    {
        //Retrieve volume id and server state
        $call = $this->retrieve_server_info($server_id);
        if($call["httpCode"] != 200)
            return $call;

        $serverState = (json_decode($call["json"], true)["server"]["state"]);
        $vol_id_attached = (json_decode($call["json"], true)["server"]["volumes"]["0"]["id"]);
        $ip_id_attached = (json_decode($call["json"], true)["server"]["public_ip"]["id"]);

        //Easy way
        if($serverState == "running")
        {
            $response = $this->execute_server_action("terminate", $server_id);

            return $response;
        }
        else if($serverState == "stopped") //Hard wa
        {
            $http_method = "DELETE";
            $endpoint = "/servers/" . $server_id;

            $response = $this->call_scaleway_api($this->token, $http_method, $endpoint, array(), array());

            if ($response["httpCode"] == 204)
            {
                //We have deleted the server and now we have to delete volume and IP manually
                $delVolRes  = $this->delete_volume($vol_id_attached);
                $delIpRes = $this->delete_ip_address($ip_id_attached);

                if($delVolRes["httpCode"] == 204)
                {
                    if ($delIpRes["httpCode"] == 204)
                        return $response;
                    else
                        return $delIpRes;
                }
                else
                    return $delVolRes;
            }
            return $response;
        }
        else
        {
            //Server may be booting or pending for an action. In this case we have to try after server get into a valid state
            return array (
                            "httpCode" => "123",
                            "json" => json_encode(array("message" => "Server is into an intermediate state! Wait for power on/off then try again!"))
                         );
        }
    }

    //Function used to suspend the server
    public function server_poweroff($server_id)
    {
        //Suspension mean power off and storing volume offline
        $result = $this->execute_server_action("poweroff", $server_id);

        return $result;
    }

    //Function to hard reboot the server
    public function server_reboot($server_id)
    {
        $result = $this->execute_server_action("reboot", $server_id);
        return $result;
    }

    //Function to power on an server
    public function server_poweron($server_id)
    {
        $result = $this->execute_server_action("poweron", $server_id);

        return $result;
    }
}

// ____  _____ ______     _______ ____  ____     __  __    _    _   _    _    ____ _____ __  __ _____ _   _ _____
/// ___|| ____|  _ \ \   / / ____|  _ \/ ___|   |  \/  |  / \  | \ | |  / \  / ___| ____|  \/  | ____| \ | |_   _|
//\___ \|  _| | |_) \ \ / /|  _| | |_) \___ \   | |\/| | / _ \ |  \| | / _ \| |  _|  _| | |\/| |  _| |  \| | | |
// ___) | |___|  _ < \ V / | |___|  _ < ___) |  | |  | |/ ___ \| |\  |/ ___ \ |_| | |___| |  | | |___| |\  | | |
//|____/|_____|_| \_\ \_/  |_____|_| \_\____/   |_|  |_/_/   \_\_| \_/_/   \_\____|_____|_|  |_|_____|_| \_| |_|
// This class is designed to work with servers as it is more easy to use than the API class.
// If an action is not implemented in this class then you'll have to use the main class and implement by yourself.
// It's a kind of wrapper for the main ScalewayAPI class which return JSON.
// It will make your life easier as it already check the response for errors and returns the right message.
class ScalewayServer
{
    protected $api = "";
    protected $srvLoc = "par1"; //let's set a default value.

    public $server_id = "";

    //This store the API result. Usefull in case of error.
    public $queryInfo = "";

    public $state_detail = "";
    public $image = array
            (
            //There are a lot more details, we keep onle those below:
            "name"        => "",
            "arch"        => "",
            "id"          => "",
            "root_volume" => array
                                (
                                    "size"        => "",
                                    "id"          => "",
                                    "volume_type" => "",
                                    "name"        => ""
                                )
        );
    public $creation_date = "";
    public $public_ip = array
            (
                "dynamic" => false,
                "id"      => "",
                "address" => ""
            );
    public $private_ip = "";
    public $id = "";
    public $dynamic_ip_required = false;
    public $modification_date = "";
    public $enable_ipv6 = false;
    public $hostname = "";
    public $state = "";
    public $bootscript = array
            (
                "id"     => "",
                "kernel" => "",
                "title"  => ""
            );
    public $location = array
            (
                "platform_id" => "",
                "node_id"     => "",
                "blade_id"    => "",
                "zone_id"     => "",
                "chassis_id"  => ""
            );
    public $ipv6 = "";
    public $commercial_type = "";
    public $tags = array();
    public $arch = "";
    public $extra_networks = array();
    public $name = "";
    public $volumes = array();
    public $security_group = array
            (
                "id"   => "",
                "name" => ""
            );
    public $organization = "";

    function __construct($token, $location)
    {
        $this->srvLoc = $location;
        $this->api = new ScalewayApi($token, $this->srvLoc);
    }

    public function setServerId($srv_id)
    {
        $this->server_id = $srv_id;
    }

    public function retrieveDetails()
    {
        $serverInfoResp = $this->api->retrieve_server_info($this->server_id);
        if($serverInfoResp["httpCode"] == 200)
        {
            $serverInfoResp = json_decode($serverInfoResp["json"], true);
            $serverInfoResp = $serverInfoResp["server"];

            $this->state_detail = $serverInfoResp["state_detail"];
            $this->image["name"] = $serverInfoResp["image"]["name"];
            $this->image["arch"] = $serverInfoResp["image"]["arch"];
            $this->image["id"] = $serverInfoResp["image"]["id"];
            $this->image["root_volume"]["size"] = $serverInfoResp["image"]["root_volume"]["size"];
            $this->image["root_volume"]["id"] = $serverInfoResp["image"]["root_volume"]["id"];
            $this->image["root_volume"]["volume_type"] = $serverInfoResp["image"]["root_volume"]["volume_type"];
            $this->image["root_volume"]["name"] = $serverInfoResp["image"]["root_volume"]["name"];
            $this->creation_date = $serverInfoResp["creation_date"];
            $this->public_ip["dynamic"] = $serverInfoResp["public_ip"]["dynamic"];
            $this->public_ip["id"] = $serverInfoResp["public_ip"]["id"];
            $this->public_ip["address"] = $serverInfoResp["public_ip"]["address"];
            $this->private_ip = $serverInfoResp["private_ip"];
            $this->id = $serverInfoResp["id"];
            $this->dynamic_ip_required = $serverInfoResp["dynamic_ip_required"];
            $this->modification_date = $serverInfoResp["modification_date"];
            $this->enable_ipv6 = $serverInfoResp["enable_ipv6"];
            $this->hostname = $serverInfoResp["hostname"];
            $this->state = $serverInfoResp["state"];
            $this->bootscript["id"] = $serverInfoResp["bootscript"]["id"];
            $this->bootscript["kernel"] = $serverInfoResp["bootscript"]["kernel"];
            $this->bootscript["title"] = $serverInfoResp["bootscript"]["title"];
            $this->location["platform_id"] = $serverInfoResp["location"]["platform_id"];
            $this->location["node_id"] = $serverInfoResp["location"]["node_id"];
            $this->location["blade_id"] = isset($serverInfoResp["location"]["blade_id"])?$serverInfoResp["location"]["blade_id"]:"";
            $this->location["zone_id"] = $serverInfoResp["location"]["zone_id"];
            $this->location["chassis_id"] = isset($serverInfoResp["location"]["chassis_id"])?$serverInfoResp["location"]["chassis_id"]:"";
            $this->ipv6 = $serverInfoResp["ipv6"];
            $this->commercial_type = $serverInfoResp["commercial_type"];
            $this->tags = $serverInfoResp["tags"];
            $this->arch = $serverInfoResp["arch"];
            $this->extra_networks = $serverInfoResp["extra_networks"];
            $this->volumes = $serverInfoResp["volumes"];
            $this->security_group["id"] = $serverInfoResp["security_group"]["id"];
            $this->security_group["name"] = $serverInfoResp["security_group"]["name"];
            $this->organization = $serverInfoResp["organization"];

            $this->queryInfo = "Success!";
            return true;
        }
        else
        {
            $this->queryInfo = $this->api->statusCodes[$serverInfoResp["httpCode"]];
            return false;
        }
    }

    public function create_new_server($name, $image_id, $commercial_type, $tags = array())
    {
        $createServerResult = $this->api->create_new_server($name, $image_id, $commercial_type, $tags);

        if($createServerResult["httpCode"] == 201)
        {
            $serverInfo = json_decode($createServerResult["json"], true);
            $serverInfo = $serverInfo["server"];

            $this->server_id = $serverInfo["id"];
            $this->retrieveDetails();

            return true;
        }
        else
        {
            $this->queryInfo =  $this->api->statusCodes[$createServerResult["httpCode"]];
            return false;
        }
    }

    public function delete_server()
    {
        $deleteServerResponse = $this->api->server_terminate($this->server_id);
        if($deleteServerResponse["httpCode"] == 202)
        {
            return true;
        }
        else
        {
            $this->queryInfo = json_decode($deleteServerResponse["json"], true)["message"];
            return false;
        }
    }

    public function poweroff_server()
    {
        $poweroff_result = $this->api->server_poweroff($this->server_id);
        if( $poweroff_result["httpCode"] == 202)
        {
            $this->retrieveDetails();
            return true;
        }
        else
        {
            $this->queryInfo = json_decode($poweroff_result["json"], true)["message"];
            return false;
        }
    }

    public function poweron_server()
    {
        $poweron_result = $this->api->server_poweron($this->server_id);
        if( $poweron_result["httpCode"] == 202)
        {
            $this->retrieveDetails();
            return true;
        }
        else
        {
            $this->queryInfo = json_decode($poweron_result["json"], true)["message"];
            return false;
        }
    }

    public function reboot_server()
    {
        $reboot_result = $this->api->server_reboot($this->server_id);
        if( $reboot_result["httpCode"] == 202)
        {
            $this->retrieveDetails();
            return true;
        }
        else
        {
            $this->queryInfo = json_decode($reboot_result["json"], true)["message"];
            return false;
        }
    }

    public function update_info_server($newPassword, $newHostname, $puttySSHkey)
    {
        if( !$this->retrieveDetails() )
        {
            $this->queryInfo = "Cand access the server. Maybe server ID is invalid?";
            return false;
        }

        if($this->state_detail != "booted" || $this->state != "running")
        {
            $this->queryInfo = "In order to change the server password it must be up and running!";
            return false;
        }

        $ipAddr = $this->public_ip["address"];

        $rsa = new Crypt_RSA();
        $rsa->loadKey($puttySSHkey);
        $rsa->setPassword();

        try
        {
            $ssh = new Net_SSH2($ipAddr);
            if (!$ssh->login("root", $rsa))
            {
                $this->queryInfo = "Net_SSH2 => failed to login to server via SSH2! => " . ($ssh->isConnected() ? 'bad username or password' : 'unable to establish connection');
                return false;
            }

            $newLoginHead = "IF8gICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgDQovIHxfICAgICAgX19fXyBfIF8g".
                "ICBfICAgICAgIF8gX18gIF8gX18gX19fICANCnwgXCBcIC9cIC8gLyBfYCB8IHwgfCB8ICAgICB8ICdfIFx8ICdfXy8gXyBcIA0".
                "KfCB8XCBWICBWIC8gKF98IHwgfF98IHwgIF8gIHwgfF8pIHwgfCB8IChfKSB8DQp8X3wgXF8vXF8vIFxfXyxffFxfXywgfCAoXy".
                "kgfCAuX18vfF98ICBcX19fLyANCiAgICAgICAgICAgICAgICAgfF9fXy8gICAgICB8X3wgICAgICAgICAgICAgIA0K";
            $ssh->exec("base64 -d <<< {$newLoginHead} > /etc/motd.head");
            $ssh->setTimeout(2);

            //Change root password
            $ssh->write("passwd\n");
            $ssh->read('Enter New UNIX password:');
            $ssh->write("{$newPassword}\n");
            $ssh->read('Retype new UNIX password:');
            $ssh->write("{$newPassword}\n");
            $ssh->read('passwd: all authentication tokens updated successfully.');
            $ssh->exec("hostname {$newHostname}\n");
            $ssh->write("\n");

            //IF you wanna play around...
            //Adding new user
            $ssh->write("adduser administrator\n"); sleep(1); //Dirty, I know
            $ssh->read("Enter new UNIX password:");
            $ssh->write("{$newPassword}\n");
            $ssh->read('Retype new UNIX password:');
            $ssh->write("{$newPassword}\n");

            $ssh->read("Full Name []:");
            $ssh->write("\n");

            $ssh->read("Room Nmber[]:");
            $ssh->write("\n");

            $ssh->read("Work Phone []:");
            $ssh->write("\n");

            $ssh->read("Home Phone []:");
            $ssh->write("\n");

            $ssh->read("Other []:");
            $ssh->write("\n");

            $ssh->read("Is this information correct? [Y/n]");
            $ssh->write("Y\n");

            //Don't forget to remove old SSH key
            $ssh->exec("rm /root/.ssh/authorized_keys");

            return true;
        }
        catch(Exception $e)
        {
           $this->queryInfo = "Net_SSH2 Exception => " . $e->getMessage();
            return false;
        }
    }

    public static function getArchByCommercialType($cType)
    {
        $len = strlen($cType);
        if($len < 2)  //to make sure we don't return default arch for null strings
            return "unknown";

        //We have two possible architectures to return: arm | x86_64
        $cType = strtolower($cType);
        if (strpos($cType, 'arm') !== false || strpos($cType, 'c1') !== false)   //if contains arm/c1 in name, it is ARM architecture
        {
            return "arm";
        }
        else
        {
            return "x86_64";
        }
    }
}

// ___ __  __    _    ____ _____ ____     __  __    _    _   _    _    ____ _____ __  __ _____ _   _ _____
//|_ _|  \/  |  / \  / ___| ____/ ___|   |  \/  |  / \  | \ | |  / \  / ___| ____|  \/  | ____| \ | |_   _|
// | || |\/| | / _ \| |  _|  _| \___ \   | |\/| | / _ \ |  \| | / _ \| |  _|  _| | |\/| |  _| |  \| | | |
// | || |  | |/ ___ \ |_| | |___ ___) |  | |  | |/ ___ \| |\  |/ ___ \ |_| | |___| |  | | |___| |\  | | |
//|___|_|  |_/_/   \_\____|_____|____/   |_|  |_/_/   \_\_| \_/_/   \_\____|_____|_|  |_|_____|_| \_| |_|
//Same thing, wrapper for images!
class ScalewayImages
{
    public $api = "";
    protected $srvLoc = "par1";

    public $images = array();
    public $queryInfo = "";

    function __construct($token, $location)
    {
        $this->srvLoc = $location;
        $this->api = new ScalewayApi($token, $this->srvLoc);
        $this->updateImages();
    }

    private function updateImages()
    {
        $this->images = array();
        $imgs = $this->api->retrieve_images();
        if($imgs["httpCode"] == 200)
        {
            $imagesArray = json_decode($imgs["json"], true);
            for($i=0; $i < count($imagesArray['images']); $i++)
            {
                $imageInfo = $imagesArray['images'][$i];
                $image = array
                        (
                            "id"     => $imageInfo["id"],
                            "name"   => $imageInfo["name"],
                            "arch"   => $imageInfo["arch"],
                            "public" => $imageInfo["public"]
                        );
                array_push($this->images, $image);
            }

            //!!!!!!
            //Don't know why but Scaleway has multiple IDs for the same image. We preffer to keep only one as we can't display thousands distributions names to client.
            //Later update: images have different kernels...
            // ?????????????
            //$this->images = $this->remove_duplicates($this->images);
            return true;
        }
        else
        {
            $this->queryInfo = json_decode($imgs["json"], true)["message"];
            return false;
        }
    }

    public function remove_duplicates($images = array())
    {
        $buffer = $images;
        $images = array();

        foreach($buffer as $key => $value)
        {
            if( !$this->in_array_custom($value, $images) )
                array_push($images, $value);
        }
        return $images;
    }

    private function in_array_custom($element, $arr = array() )
    {
        //It's custom because we have to compare two dmenssion arrays.
        foreach($arr as $k => $v)
        {
            if($v["name"] == $element["name"])
                return true;
        }
        return false;
    }

    public function getImagesByArch($arch, $public = true)
    {
        if($this->updateImages())
        {
            $buffer = $this->images;
            $this->images = array();

            foreach($buffer as $key => $value)
            {
                //$value will be an array which has ID, NAME, ARCH and PUBLIC of image
                if($value["arch"] == $arch && $value["public"] == $public)
                {
                    array_push($this->images, $value);
                }
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    public function getImageByName($arch, $name, $public = true)
    {
        if($this->updateImages())
        {
            $buffer = $this->images;
            $this->images = array();

            foreach($buffer as $key => $value)
            {
                //$value will be an array which has ID, NAME, ARCH and PUBLIC of image
                if($value["name"] == $name && $value["public"] == $public && $value["arch"] == $arch)
                {
                    array_push($this->images, $value);
                }
            }

            if( count($this->images) < 1)
            {
                $this->queryInfo = "Image was not found on Scaleway database!";
                return false;
            }
            else
            {
                return true;
            }
        }
        else
            return false;
    }

    public function getImageById($id, $public = true)
    {
        if($this->updateImages())
        {
            $buffer = $this->images;
            $this->images = array();

            foreach($buffer as $key => $value)
            {
                //$value will be an array which has ID, NAME, ARCH and PUBLIC of image
                if ($value["id"] == $id && $value["public"] == $public)
                {
                    array_push($this->images, $value);
                }
            }
            return true;
        }
        else
            return false;
    }
}

//__        ___   _ __  __  ____ ____     ____  _   _ _     _     _____
//\ \      / / | | |  \/  |/ ___/ ___|   |  _ \| | | | |   | |   |__  /
// \ \ /\ / /| |_| | |\/| | |   \___ \   | |_) | | | | |   | |     / /
//  \ V  V / |  _  | |  | | |___ ___) |  |  _ <| |_| | |___| |___ / /_
//   \_/\_/  |_| |_|_|  |_|\____|____/   |_| \_\\___/|_____|_____/____|
//All WHMCS required functions are bellow

//    _    ____  __  __ ___ _   _ ___ ____ _____ ____      _  _____ ___  ____
//   / \  |  _ \|  \/  |_ _| \ | |_ _/ ___|_   _|  _ \    / \|_   _/ _ \|  _ \
//  / _ \ | | | | |\/| || ||  \| || |\___ \ | | | |_) |  / _ \ | || | | | |_) |
// / ___ \| |_| | |  | || || |\  || | ___) || | |  _ <  / ___ \| || |_| |  _ <
///_/   \_\____/|_|  |_|___|_| \_|___|____/ |_| |_| \_\/_/   \_\_| \___/|_| \_\

function Scaleway_MetaData()
{
    return array
    (
        'DisplayName' => 'Scaleway',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
        'DefaultNonSSLPort' => '1111', // Default Non-SSL Connection Port
        'DefaultSSLPort' => '1112', // Default SSL Connection Port
        'ServiceSingleSignOnLabel' => 'Login to Panel as User',
        'AdminSingleSignOnLabel' => 'Login to Panel as Admin',
    );
}

function Scaleway_ConfigOptions()
{
    $commercial_types = array();
    foreach(ScalewayApi::$commercialTypes as $ctype => $cval)
    {
        array_push($commercial_types,($ctype . " - " . $cval));
    }

    return array
    (
        // a password field type allows for masked text input
        'Token' => array
        (
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Scaleway secret token - used to access your account',
        ),

        // the yesno field type displays a single checkbox option
        'IPv6' => array
        (
            'Type' => 'yesno',
            'Description' => 'Do you want to enable IPv6? Check if available for this server!',
        ),

        // the dropdown field type renders a select menu of options
        'Commercial type' => array
        (
            'Type' => 'dropdown',
            'Options' => $commercial_types,
            'Description' => 'Choose one',
        ),

        // the textarea field type allows for multi-line text input
        'Scaleway SSH Key (Putty format [.ppk])' => array
        (
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '60',
            'Description' => "Coz' we have to login to server first to set root password and enable root login!",
        ),

        // a text field type allows for single line text input
        'Admin username' => array(
            'Type' => 'text',
            'Size' => '25',
            'Default' => '1024',
            'Description' => 'Enter an username of an WHMCS Administrator',
        ),
    );
}

function Scaleway_CreateAccount(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $ipv6 = ($params["configoption2"]=="on"?("true"):("false"));
        $commercial_type = array_keys(ScalewayApi::$commercialTypes)[$params["configoption3"]]; //it provide only index of commercial type so we fetch full name from predefined array
        $ssh_key = $params["configoption4"];
        $arch = ScalewayServer::getArchByCommercialType($commercial_type);

        $service_id = $params["serviceid"];
        $user_id = $params["userid"];
        $productid = $params["pid"];

        $hostname = explode(".", $params["domain"])[0];
        $password = $params["password"];

        $os_name = $params["customfields"]["Operating system"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);

        $scwImage = new ScalewayImages($token, $location);

        if( !$scwImage->getImageByName( $arch, $os_name) )
        {
            return "Failed. Image selected is not available!\n Error msg: " . $scwImage->queryInfo;
        }

        $image_id = $scwImage->images["0"]["id"];
        if( strlen($image_id) < 25 )
            return "Invalid image and/or designated architecture";

        $tags = array("uid:" . $user_id, "pid:" . $productid, "serviceid:" . $service_id, "serverid:" . $params["serverid"]);

        //Check if the current server were terminated
        if( $scwServer->retrieveDetails() == true )
            return "Error! Please terminate current server then create another server again!";

        //Now we have to create the new server and update Server ID field
        if($scwServer->create_new_server($hostname, $image_id, $commercial_type, $tags ))
        {
            //If server grated, retrive his id and insert to Server ID field, so next time we know his ID.
            $command = "updateclientproduct";
            $adminuser = $params["configoption5"];
            $values["serviceid"] = $service_id;

            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["customfields"] = base64_encode(serialize(array("Server ID"=> $scwServer->server_id )));
            localAPI($command, $values, $adminuser);
        }
        else
        {
            //Log request to understand why it failed
            $request = "";
            //User and service info
            $request .= "Service ID: " . $service_id . "\n";
            $request .= "User ID: " . $user_id . "\n";
            $request .= "Product ID: " . $productid . "\n";

            //Config info
            $request .= "IPv6: " . $ipv6 . "\n";
            $request .= "Commercial type: " . $commercial_type . "\n";
            $request .= "SSH Key length: " . strlen($ssh_key) . "\n";

            //And finally server info
            $request .= "Hostname: " . $hostname . "\n";
            $request .= "Password: " . $password . "\n";
            $request .= "OS Name: " . $os_name . "\n";
            $request .= "Arch: " . $arch . "\n";
            $request .= "Image ID: " . $image_id . "\n";
            $request .= "Curr server ID: " . $curr_server_id . "\n";

            //Response
            $response = $scwServer->queryInfo;

            //Send error to Utilities >> Log >> Module Log
            logModuleCall('Scaleway', __FUNCTION__, $request, "blabla", $response);

            return "Failed to create server! Check Utilites >> Log >> Module log. Details: " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Scaleway',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    return 'success';
}

function Scaleway_SuspendAccount(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return "Invalid server id!";

        if($scwServer->poweroff_server())
        {
            $command = "updateclientproduct";
            $adminuser = $params["configoption5"];
            $values["serviceid"] = $params["serviceid"];

            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["status"] = "Suspended";

            localAPI($command, $values, $adminuser);
        }
        else
        {
            return "Failed to suspend server! " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    return 'success';
}

function Scaleway_UnsuspendAccount(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return "Invalid server id!";

        if($scwServer->poweron_server())
        {
            $command = "updateclientproduct";
            $adminuser = $params["configoption5"];
            $values["serviceid"] = $params["serviceid"];

            $values["status"] = "Active";

            localAPI($command, $values, $adminuser);
        }
        else
        {
            return "Failed to unsuspend server! " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    return 'success';
}

function Scaleway_RebootServer(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return "Invalid server id!";

        if($scwServer->reboot_server())
        {
            return "success";
        }
        else
        {
            return "Failed to reboot server! " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    return 'success';
}

function Scaleway_TerminateAccount(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return "Invalid server id!";

        if($scwServer->delete_server())
        {
            $command = "updateclientproduct";
            $adminuser = $params["configoption5"];
            $values["serviceid"] = $params["serviceid"];

            //We only have to update server ID, the rest of field will be automaticall updated on refresh.
            $values["status"] = "Terminated";
            $values["customfields"] = base64_encode(serialize(array("Server ID"=> "terminated-" . $curr_server_id )));  //keep history of server ID in case client made nasty things from your server

            localAPI($command, $values, $adminuser);
        }
        else
        {
            return "Failed to terminate server server! " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    return 'success';
}

function Scaleway_AdminCustomButtonArray()
{
    return array(
        "Reboot server"=> "RebootServer",
        "Update stats" => "updateStats",
        "GetARMimgsList" => "GetArmImagesList",
        "GetXimgsList" => "GetX86_64ImagesList",
    );
}

function Scaleway_updateStats(array $params)
{
    try
    {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        $scwServer->setServerId($server_id);

        if( !$scwServer->retrieveDetails() )
        {
            return "Can't get server info! " . $scwServer->queryInfo;
        }

        //Updating fields with data returned from Scaleway.
        $command = "updateclientproduct";
        $adminuser = $params["configoption5"];
        $values["serviceid"] = $params["serviceid"];

        $values["customfields"] = base64_encode(serialize(array
            (
                //Those are just custom fields!
                "Operating system"=>$scwServer->image["name"]
            )
        ));
        $values["dedicatedip"] = $scwServer->public_ip["address"];
        //$values["serviceusername"] = "administrator";
        $values["domain"] = $scwServer->hostname;

        localAPI($command,$values,$adminuser);

        //IF root password was not set, do it now and mark as changed in case of success by checking the "Root password updated" check bok.
        if($params["customfields"]["Root password updated"] == "" && $scwServer->state == "running" && $scwServer->state_detail == "booted") // if root password was not set
        {
            $sshKey = $ssh_key = $params["configoption4"];
            $newHostname = explode(".", $params["domain"])[0];
            $newPassword = $params["password"];

            if( $scwServer->update_info_server($newPassword, $newHostname, $sshKey) )
            {
                $command = "updateclientproduct";
                $adminuser = $params["configoption5"];
                $values["serviceid"] = $params["serviceid"];

                $values["serviceusername"] = "administrator";
                //We only have to update server ID, the rest of field will be automaticall updated on refresh.
                $values["customfields"] = base64_encode(serialize(array("Root password updated" => "on" )));
                localAPI($command, $values, $adminuser);

                return 'success';
            }
            else
            {
                //Log request to understand why it failed
                $request = "";
                $request .= "Server ID: " . $server_id . "\n";
                $request .= "SSH Key length: \n" . $ssh_key . "\n\n";

                //Response
                $response = $scwServer->queryInfo;

                //Send error to Utilities >> Log >> Module Log
                logModuleCall('Scaleway', __FUNCTION__, $request, "blabla", $response);
                return "Failed to set new password. Also Utilities >> Log >> Module Log. Details: " . $scwServer->queryInfo;
            }
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
    return 'success';
}

function Scaleway_AdminServicesTabFields(array $params)
{
    try
    {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        $scwServer->setServerId($server_id);

        if( !$scwServer->retrieveDetails() )
        {
            $command = "updateclientproduct";
            $adminuser = $params["configoption5"];
            $values["serviceid"] = $params["serviceid"];
            $values["dedicatedip"] = "unknown";
            localAPI($command, $values, $adminuser);

            return array("Server error" => $scwServer->queryInfo);
        }

        if($params["customfields"]["Root password updated"] != "on" && $scwServer->state == "running" && $scwServer->state_detail == "booted") // if root password was not set
        {
            $sshKey = $ssh_key = $params["configoption4"];
            $newHostname = explode(".", $params["domain"])[0];
            $newPassword = $params["password"];

            if( $scwServer->update_info_server($newPassword, $newHostname, $sshKey) )
            {
                $command = "updateclientproduct";
                $adminuser = $params["configoption5"];
                $values["serviceid"] = $params["serviceid"];

                $values["serviceusername"] = "administrator";
                //We only have to update server ID, the rest of field will be automaticall updated on refresh.
                $values["customfields"] = base64_encode(serialize(array("Root password updated"=> "on" )));
                localAPI($command, $values, $adminuser);
            }
            else
            {
                //return array("Error returned" => "Failed to set new password. Details: " . $scwServer->queryInfo);
            }
        }

        //Updating fields with data returned from Scaleway.
        $command = "updateclientproduct";
        $adminuser = $params["configoption5"];
        $values["serviceid"] = $params["serviceid"];

        $values["customfields"] = base64_encode(serialize(array ( "Operating system"=>$scwServer->image["name"] ) ));
        $values["dedicatedip"] = $scwServer->public_ip["address"];
        $values["domain"] = $scwServer->hostname;

        //Need to analyze. It make same variables become undefined...
        localAPI($command, $values, $adminuser);

        // Return an array based on the function's response.
        return array(
            'Server name' => $scwServer->hostname,
            'Server state' => $scwServer->state,
            'Server state detail' => $scwServer->state_detail,
            'Root volume' => "Name: " . $scwServer->image["root_volume"]["name"] . " -- Size: " . $scwServer->image["root_volume"]["size"] . " -- ID: " . $scwServer->image["root_volume"]["id"] . " -- Type: " . $scwServer->image["root_volume"]["volume_type"],
            'Image' => "Name: " . $scwServer->image["name"] . " -- ID: " . $scwServer->image["id"],
            'Creation date' =>$scwServer->creation_date,
            'Public IP v4' => "Address: " . $scwServer->public_ip["address"] . " -- ID: " . $scwServer->public_ip["id"] . " -- " . '<form><input type="submit" name="changeIpButton" value="Change IP" onClick="alert(\'Not implemented\')"></form>',
            'Private IP v4' => "Address: " . $scwServer->private_ip ,
            'Dynamic IP Required' => $scwServer->dynamic_ip_required,
            'Modification date' => $scwServer->modification_date,
            'IPv6 Enabled' => $scwServer->enable_ipv6,
            'IPv6' => $scwServer->ipv6,
            'Bootscript' => "ID: " . $scwServer->bootscript["id"] . " -- Kernel: " . $scwServer->bootscript["kernel"] . " -- Title: " . $scwServer->bootscript["title"],
            'Location' => "Platform ID: " . $scwServer->location["platform_id"] . " -- Node ID: " . $scwServer->location["node_id"] . " -- Blade ID: " . $scwServer->location["blade_id"] . " -- Zone ID: " . $scwServer->location["zone_id"] . " -- Chassis ID: " . $scwServer->location["chassis_id"],
            'Commercial type' => $scwServer->commercial_type,
            'Tags' => implode(",", $scwServer->tags),
            'Architecture' => $scwServer->arch,
            'Extra networks' => (json_encode($scwServer->extra_networks) == "Array"?$scwServer->extra_networks:""),
            'Volumes' => (json_encode($scwServer->volumes) == "Array"?$scwServer->volumes:""),
            'Security group' => "Name:" . $scwServer->security_group["name"] . " -- ID: " . $scwServer->security_group["id"],
        );
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    return array();
}

function Scaleway_GetArmImagesList(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $location = $params["customfields"]["Location"];
        $scwImage = new ScalewayImages($token, $location);

        if( !$scwImage->getImagesByArch( "arm") )
        {
            return "Failed. This is bad: " . $scwImage->queryInfo;
        }

        $scwImage->images = $scwImage->remove_duplicates($scwImage->images);

        $images_concat = "";
        foreach ($scwImage->images as $img)
        {
            $images_concat .= $img["name"] . ",";
        }
        return $images_concat;
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Scaleway',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

function Scaleway_GetX86_64ImagesList(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $location = $params["customfields"]["Location"];

        $scwImage = new ScalewayImages($token, $location);

        if( !$scwImage->getImagesByArch( "x86_64") )
        {
            return "Failed. This is bad: " . $scwImage->queryInfo;
        }

        $scwImage->images = $scwImage->remove_duplicates($scwImage->images);

        $images_concat = "";
        foreach ($scwImage->images as $img)
        {
            $images_concat .= $img["name"] . ",";
        }

        return $images_concat;
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Scaleway',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

//  ____ _     ___ _____ _   _ _____
// / ___| |   |_ _| ____| \ | |_   _|
//| |   | |    | ||  _| |  \| | | |
//| |___| |___ | || |___| |\  | | |
// \____|_____|___|_____|_| \_| |_|

function Scaleway_ClientAreaCustomButtonArray()
{
    return array
    (
        "Update stats" => "ClientUpdateStatsFunction",
        "Reboot server" => "ClientRebootServer",
        "Power OFF" => "ClientPowerOffServer",
        "Power ON" => "ClientPowerOnServer",
        "Snapshoot" => "ClientSnapshootServer"
    );
}

function Scaleway_ClientRebootServer(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return " - Error details: invalid server id!";

        if($scwServer->reboot_server())
        {
            return "success";
        }
        else
        {
            return "- Failed to reboot server! Error details: " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function Scaleway_ClientPowerOffServer(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return " - Error details: invalid server id!";

        if($scwServer->poweroff_server())
        {
            return "success";
        }
        else
        {
            return "- Failed to poweroff server! Error details: " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function Scaleway_ClientPowerOnServer(array $params)
{
    try
    {
        $token = $params["configoption1"];
        $curr_server_id = $params["customfields"]["Server ID"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        if(strlen($curr_server_id) == 36)
            $scwServer->setServerId($curr_server_id);
        else
            return " - Error details: invalid server id!";

        if($scwServer->poweron_server())
        {
            return "success";
        }
        else
        {
            return "- Failed to poweron server! Error details: " . $scwServer->queryInfo;
        }
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function Scaleway_ClientSnapshootServer(array $params)
{
    return "- Not implemented yet!";
}

function Scaleway_ClientArea(array $params)
{
    try
    {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        $scwServer->setServerId($server_id);
        $scwServer->retrieveDetails();

        //IF root password was not set, do it now and mark as changed in case of success by checking the "Root password updated" check bok.
        if($params["customfields"]["Root password updated"] == "" && $scwServer->state == "running" && $scwServer->state_detail == "booted") // if root password was not set
        {
            $sshKey = $ssh_key = $params["configoption4"];
            $newHostname = explode(".", $params["domain"])[0];
            $newPassword = $params["password"];

            if( $scwServer->update_info_server($newPassword, $newHostname, $sshKey) )
            {
                $command = "updateclientproduct";
                $adminuser = $params["configoption5"];
                $values["serviceid"] = $params["serviceid"];

                $values["serviceusername"] = "administrator";
                //We only have to update server ID, the rest of field will be automaticall updated on refresh.
                $values["customfields"] = base64_encode(serialize(array("Root password updated"=> "on" )));
                localAPI($command, $values, $adminuser);

                return array("Updateeed!");
            }
            else
            {
                return array("Failed to set new password. Details: " . $scwServer->queryInfo);
            }
        }

        return array(
            'templateVariables' => array(
                'sid' =>$scwServer->server_id,
                'sname' => $scwServer->hostname,
                'sstate' => $scwServer->state,
                'sstatedetail' => $scwServer->state_detail,
                'rootvolume' => "Size: " . $scwServer->image["root_volume"]["size"]/1000000000 . "GB" . " Type: " . $scwServer->image["root_volume"]["volume_type"],
                'image' => $scwServer->image["name"],
                'creationdate' =>$scwServer->creation_date,
                'publicipv4' => "Address: " . $scwServer->public_ip["address"],
                'privateipv4' => "Address: " . $scwServer->private_ip ,
                'dynamiciprequired' => $scwServer->dynamic_ip_required,
                'modificationdate' => $scwServer->modification_date,
                'ipv6enabled' => $scwServer->enable_ipv6,
                'ipv6' => $scwServer->ipv6,
                'bootscript' =>$scwServer->bootscript["kernel"],
                'location' => "Platform ID: " . $scwServer->location["platform_id"] . " -- Node ID: " . $scwServer->location["node_id"] . " -- Blade ID: " . $scwServer->location["blade_id"] . " -- Zone ID: " . $scwServer->location["zone_id"] . " -- Chassis ID: " . $scwServer->location["chassis_id"],
                'commercialtype' => $scwServer->commercial_type,
                'tags' => implode(",", $scwServer->tags),
                'architecture' => $scwServer->arch,
                'extranetworks' => (json_encode($scwServer->extra_networks) == "Array"?$scwServer->extra_networks:""),
                'volumes' => (json_encode($scwServer->volumes) == "Array"?$scwServer->volumes:""),
                'securitygroup' => "Name:" . $scwServer->security_group["name"] . " -- ID: " . $scwServer->security_group["id"],
            ));
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ));
    }
}

function Scaleway_ClientUpdateStatsFunction(array $params)
{
    try
    {
        $server_id = $params["customfields"]["Server ID"];
        $token = $params["configoption1"];
        $location = $params["customfields"]["Location"];

        $scwServer = new ScalewayServer($token, $location);
        $scwServer->setServerId($server_id);
        $scwServer->retrieveDetails();

        //IF root password was not set, do it now and mark as changed in case of success by checking the "Root password updated" check bok.
        if($params["customfields"]["Root password updated"] == "" && $scwServer->state == "running" && $scwServer->state_detail == "booted") // if root password was not set
        {
            $sshKey = $ssh_key = $params["configoption4"];
            $newHostname = explode(".", $params["domain"])[0];
            $newPassword = $params["password"];

            if( $scwServer->update_info_server($newPassword, $newHostname, $sshKey) )
            {
                $command = "updateclientproduct";
                $adminuser = $params["configoption5"];
                $values["serviceid"] = $params["serviceid"];

                $values["serviceusername"] = "administrator";
                //We only have to update server ID, the rest of field will be automaticall updated on refresh.
                $values["customfields"] = base64_encode(serialize(array("Root password updated"=> "on" )));
                localAPI($command, $values, $adminuser);

                return array("Updateeed!");
            }
            else
            {
                return array("Failed to set new password. Details: " . $scwServer->queryInfo);
            }
        }

        return array(
            'templateVariables' => array(
                'sid' =>$scwServer->server_id,
                'sname' => $scwServer->hostname,
                'sstate' => $scwServer->state,
                'sstatedetail' => $scwServer->state_detail,
                'rootvolume' => "Size: " . $scwServer->image["root_volume"]["size"]/1000000000 . "GB" . " Type: " . $scwServer->image["root_volume"]["volume_type"],
                'image' => $scwServer->image["name"],
                'creationdate' =>$scwServer->creation_date,
                'publicipv4' => "Address: " . $scwServer->public_ip["address"],
                'privateipv4' => "Address: " . $scwServer->private_ip ,
                'dynamiciprequired' => $scwServer->dynamic_ip_required,
                'modificationdate' => $scwServer->modification_date,
                'ipv6enabled' => $scwServer->enable_ipv6,
                'ipv6' => $scwServer->ipv6,
                'bootscript' =>$scwServer->bootscript["kernel"],
                'location' => "Platform ID: " . $scwServer->location["platform_id"] . " -- Node ID: " . $scwServer->location["node_id"] . " -- Blade ID: " . $scwServer->location["blade_id"] . " -- Zone ID: " . $scwServer->location["zone_id"] . " -- Chassis ID: " . $scwServer->location["chassis_id"],
                'commercialtype' => $scwServer->commercial_type,
                'tags' => implode(",", $scwServer->tags),
                'architecture' => $scwServer->arch,
                'extranetworks' => (json_encode($scwServer->extra_networks) == "Array"?$scwServer->extra_networks:""),
                'volumes' => (json_encode($scwServer->volumes) == "Array"?$scwServer->volumes:""),
                'securitygroup' => "Name:" . $scwServer->security_group["name"] . " -- ID: " . $scwServer->security_group["id"],
            ));
    }
    catch (Exception $e)
    {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'provisioningmodule',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        // In an error condition, display an error page.
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => array(
                'usefulErrorHelper' => $e->getMessage(),
            ));
    }
}