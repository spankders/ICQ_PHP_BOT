<?php
include('rtf.class.php');
/**
 * http://intrigue.ru/forum/ - disscus this package here.
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * See http://www.gnu.org/copyleft/lesser.html
 * 
 * @author Sergey Akudovich
 * @version 1.2b
 * @package WebIcqPro
 *
 */

/**
 * Layer for TLV (Type-Length-Value) data format 
 * @access private
 */
class WebIcqPro_TLV {
	/**
	 * Last error message
	 *
	 * @var string
	 */
	public $error;

	/**
	 * Pack data to TLV
	 * 
	 * Use this method to create TLVs. available formats 'c', 'n', 'N', 'a*', 'H*'
	 *
	 * @access private
	 * @param integer $type
	 * @param string $value
	 * @param string $format
	 * @return string binary
	 */
	protected function packTLV($type, $value='', $format = 'a*')
	{
		if (in_array($format, array('c', 'n', 'N', 'a*', 'H*')))
		{
			switch ($format) {
				case 'c':
					$len = 1;
					break;
				case 'n':
					$len = 2;
					break;
				case 'N':
					$len = 4;
					break;
				case 'H*':
					$len = strlen($value)/2;
					break;

				default:
					$len = strlen($value);
					break;
			}
			return pack('nn'.$format, $type, $len, $value);
		}
		$this->error = 'Warning: packTLV unknown data format: '.$format;
		return false;
	}

	/**
	 * Unpack data from TLV
	 * 
	 * Use this method to extract TLV binary data
	 *
	 * @access private
	 * @param string $data
	 * @return string
	 */
	protected function unpackTLV(& $data)
	{
		if (strlen($data)>=4)
		{
			$result = unpack('ntype/nsize/a*data', $data);
			$data = $result['data'];
			$result['data'] = substr($data, 0 , $result['size']);
			$data = substr($data, $result['size']);
			return $result;
		}
		$this->error = 'Error: unpackTLV brocken TLV';
		return false;
	}

	/**
	 * Unpuck data from set of TLVs
	 * 
	 * Use this method to extract a set of TLVs binary data to an associated array where array indexes are TLV type and value TLV data
	 *
	 * @access private
	 * @param string $data
	 * @return array
	 */
	protected function splitTLVsToArray($data, $fixqip = false)
	{
		$tlv = array();
		while (strlen($data) > 0)
		{
			$tlv_data = $this->unpackTLV($data);
			if ($tlv_data)
			{
				$tlv[$tlv_data['type']] = $tlv_data['data'];
			}
			elseif($fixqip)
			{
				if(isset($tlv[0x0101]))
				{
					$tlv[0x0101] = substr($tlv[0x0101], 0 , -1);
				}
				return $tlv;
			}
			else
			{
				return false;
			}
		}
		return $tlv;
	}
}

/**
 * Layer for SNAC data format
 * @access private
 */
class WebIcqPro_SNAC extends WebIcqPro_TLV {

	/**
	 * Request counter
	 *
	 * This variable store request id for SNAC
	 * 
	 * @access private
	 * @var integer
	 */
	private $request_id;

	/**
	 * Capability for message
	 *
	 * This variable store current method of capability for ibcm
	 * 
	 * @access private
	 * @var string
	 */
	private $ibcm_capabilities;

	/**
	 * Message type
	 * 
	 * This variable store current type of ibcm
	 *
	 * @access private
	 * @var string
	 */
	private $ibcm_type;

	/**
	 * Client type.
	 * 
	 * This variable used to store current client name.
	 *
	 * @access private
	 * @var string
	 */
	protected $agent;

	/**
	 * Array of known SNACs
	 * 
	 * Full list of supported SNACs. All added handlers should be added here.
	 *
	 * @access private
	 * @var array
	 */
	protected $snac_names = array(
	0x01 => array( // Generic service controls
	0x02 => 'ClientReady',
	0x03 => 'ServerFamilies',
	0x06 => 'ClientRates',
	0x07 => 'ServerRates',
	0x08 => 'ClientRatesAck',
	0x0E => 'ClientRequestSelfInfo',
	0x0F => 'ServerResponseSelfInfo',
	0x11 => 'ClientIdleTime',
	0x17 => 'ClientFamiliesVersions',
	0x18 => 'ServerFamiliesVersions',
	0x1E => 'ClientStatus',
	'version' => 0x04
	),
	0x02 => array( // Location services
	0x02 => 'ClientLocationRights',
	0x03 => 'ServerLocationRights',
	0x04 => 'ClientLocationInfo',
	'version' => 0x01
	),
	0x03 => array( // Buddy List management service
	0x02 => 'ClientBuddylistRights',
	0x03 => 'ServerBuddylistRights',
	'version' => 0x01
	),
	0x04 => array( // ICBM (messages) service
	0x04 => 'ClientIBCMRights',
	0x05 => 'ServerIBCMRights',
	0x06 => 'ClientIBCM',
	0x07 => 'ServerIBCM',
	0x0B => 'ClientIBCMAck',
	'version' => 0x01
	),
	0x09 => array( // Privacy management service
	0x02 => 'ClientPrivicyRights',
	0x02 => 'ServerPrivicyRights',
	'version' => 0x01
	),
	0x13 => array( // Server Side Information (SSI) service
	0x02 => 'ClientSSIRights',
	0x02 => 'ServerSSIRights',
	0x05 => 'ServerSSICheckout',
	0x07 => 'ServerSSIActivate',
	0x0F => 'ServerSSIModificationDate',
	'version' => 0x04
	),
	0x15 => array( // ICQ specific extensions service
	0x02 => 'ClientMetaData',
	0x03 => 'ServerMetaData',
	'version' => 0x01
	),
	0x17 => array( // Authorization/registration service
	0x02 => 'ClientMd5Login',
	0x03 => 'ServerMd5LoginReply',
	0x06 => 'ClientMd5Request',
	0x07 => 'ServerMd5Response',
	'version' => 0x01
	)
	);

	protected $protocol_version = 8;
	protected $capability_flag = '03000000';

	private $rates;
	protected $rates_groups;

	private $login_errors = array(
	0x0001 => 'Invalid nick or password',
	0x0002 => 'Service temporarily unavailable',
	0x0003 => 'All other errors',
	0x0004 => 'Incorrect nick or password, re-enter',
	0x0005 => 'Mismatch nick or password, re-enter',
	0x0006 => 'Internal client error (bad input to authorizer)',
	0x0007 => 'Invalid account',
	0x0008 => 'Deleted account',
	0x0009 => 'Expired account',
	0x000A => 'No access to database',
	0x000B => 'No access to resolver',
	0x000C => 'Invalid database fields',
	0x000D => 'Bad database status',
	0x000E => 'Bad resolver status',
	0x000F => 'Internal error',
	0x0010 => 'Service temporarily offline',
	0x0011 => 'Suspended account',
	0x0012 => 'DB send error',
	0x0013 => 'DB link error',
	0x0014 => 'Reservation map error',
	0x0015 => 'Reservation link error',
	0x0016 => 'The users num connected from this IP has reached the maximum',
	0x0017 => 'The users num connected from this IP has reached the maximum (reservation)',
	0x0018 => 'Rate limit exceeded (reservation). Please try to reconnect in a few minutes',
	0x0019 => 'User too heavily warned',
	0x001A => 'Reservation timeout',
	0x001B => 'You are using an older version of ICQ. Upgrade required',
	0x001C => 'You are using an older version of ICQ. Upgrade recommended',
	0x001D => 'Rate limit exceeded. Please try to reconnect in a few minutes',
	0x001E => 'Can`t register on the ICQ network. Reconnect in a few minutes',
	0x0020 => 'Invalid SecurID',
	0x0022 => 'Account suspended because of your age (age < 13)'
	);

	protected $substatuses = array(
	'STATUS_WEBAWARE'   => 0x0001,
	'STATUS_SHOWIP'     => 0x0002,
	'STATUS_BIRTHDAY'   => 0x0008,
	'STATUS_WEBFRONT'   => 0x0020,
	'STATUS_DCDISABLED' => 0x0100,
	'STATUS_DCAUTH'     => 0x1000,
	'STATUS_DCCONT'     => 0x2000
	);

	protected $statuses = array(
	'STATUS_ONLINE'     => 0x0000,
	'STATUS_AWAY'       => 0x0001,
	'STATUS_DND'        => 0x0002,
	'STATUS_NA'         => 0x0004,
	'STATUS_OCCUPIED'   => 0x0010,
	'STATUS_FREE4CHAT'  => 0x0020,
	'STATUS_INVISIBLE'  => 0x0100
	);

	protected $status_message = '';

	protected $capabilities = array(
	//	'094600004C7F11D18222444553540000', //  Uncknown
	//	'0946134D4C7F11D18222444553540000', //  Setting this lets AIM users receive messages from ICQ users, and ICQ users receive messages from AIM users. It also lets ICQ users show up in buddy lists for AIM users, and AIM users show up in buddy lists for ICQ users. And ICQ privacy/invisibility acts like AIM privacy, in that if you add a user to your deny list, you will not be able to see them as online (previous you could still see them, but they couldn't see you.
	'094613444C7F11D18222444553540000', //  Something called "route finder". Currently used only by ICQ2K clients.
	'094613494C7F11D18222444553540000', //	Client supports channel 2 extended, TLV(0x2711) based messages. Currently used only by ICQ clients. ICQ clients and clones use this GUID as message format sign.
	//	'1A093C6CD7FD4EC59D51A6474E34F5A0', //  Uncknown
	//	'0946134E4C7F11D18222444553540000', //	Client supports UTF-8 messages. This capability currently used by AIM service and AIM clients.
	'97B12751243C4334AD22D6ABF73F1492', //  Client supports RTF messages. This capability currently used by ICQ service and ICQ clients.
	//	'563FC8090B6f41BD9F79422609DFA2F3', //	Unknown capability This capability currently used only by ICQLite/ICQ2Go clients.

	//		'094600004C7F11D18222444553540000',
	//		'1A093C6CD7FD4EC59D51A6474E34F5A0',
	//		'D4A611D08F014EC09223C5B6BEC6CCF0'
	);

	protected $user_agent_capability = array(
	'miranda'   => '4D6972616E64614D0004000200030700',
	'sim'       => '53494D20636C69656E74202000090402',
	'trillian'  => '97B12751243C4334AD22D6ABF73F1409',
	'licq'      => '4c69637120636c69656e742030303030',
	'kopete'    => '4b6f7065746520494351202030303030',
	'micq'      => '6d49435120A920522e4b2e2030303030',
	'andrq'     => '265251696e7369646530303030303030',
	'randq'     => '522651696e7369646530303030303030',
	'mchat'     => '6d436861742069637120303030303030',
	'jimm'      => '4a696d6d203030303030303030303030',
	'macicq'    => 'dd16f20284e611d490db00104b9b4b7d',
	'icqlite'   => '178C2D9BDAA545BB8DDBF3BDBD53A10A',
	//		'qip'       => '563FC8090B6F41514950203230303561',
	//		'qippda'    => '563FC8090B6F41514950202020202021',
	//		'qipmobile' => '563FC8090B6F41514950202020202022',
	//		'anastasia' => '44E5BFCEB096E547BD65EFD6A37E3602',
	//		'icq2001'   => '2e7a6475fadf4dc8886fea3595fdb6df',
	//		'icq2002'   => '10cf40d14c7f11d18222444553540000',
	//		'IcqJs7     => '6963716A202020202020202020202020',
	//		'IcqJs7sec  => '6963716A2053656375726520494D2020',
	//		'TrilCrypt  => 'f2e7c7f4fead4dfbb23536798bdf0000',
	//		'SimOld     => '97b12751243c4334ad22d6abf73f1400',
	//		'Im2        => '74EDC33644DF485B8B1C671A1F86099F',
	//		'Is2001     => '2e7a6475fadf4dc8886fea3595fdb6df',
	//		'Is2002     => '10cf40d14c7f11d18222444553540000',
	//		'Comm20012  => 'a0e93f374c7f11d18222444553540000',
	//		'StrIcq     => 'a0e93f374fe9d311bcd20004ac96dd96',
	//		'AimIcon    => '094613464c7f11d18222444553540000',
	//		'AimDirect  => '094613454c7f11d18222444553540000',
	//		'AimChat    => '748F2420628711D18222444553540000',
	//		'Uim        => 'A7E40A96B3A0479AB845C9E467C56B1F',
	//		'Rambler    => '7E11B778A3534926A80244735208C42A',
	//		'Abv        => '00E7E0DFA9D04Fe19162C8909A132A1B',
	//		'Netvigator => '4C6B90A33D2D480E89D62E4B2C10D99F',
	//		'tZers      => 'b2ec8f167c6f451bbd79dc58497888b9',
	//		'HtmlMsgs   => '0138ca7b769a491588f213fc00979ea8',
	//		'SimpLite   => '53494D5053494D5053494D5053494D50',
	//		'SimpPro    => '53494D505F50524F53494D505F50524F',
	'webicqpro' => '57656249637150726f2076302e336200'
	);

	/**
	 * Set of - X Statuses.
	 * @thanks �_� ���� (http://intrigue.ru/forum/index.php?action=profile;u=190)
	 * 
	 * @var array
	 */
	protected $x_statuses = array(
	"Angry"               => '01D8D7EEAC3B492AA58DD3D877E66B92',
	"Taking a bath"       => '5A581EA1E580430CA06F612298B7E4C7',
	"Tired"               => '83C9B78E77E74378B2C5FB6CFCC35BEC',
	"Party"               => 'E601E41C33734BD1BC06811D6C323D81',
	"Drinking beer"       => '8C50DBAE81ED4786ACCA16CC3213C7B7',
	"Thinking"            => '3FB0BD36AF3B4A609EEFCF190F6A5A7F',
	"Eating"              => 'F8E8D7B282C4414290F810C6CE0A89A6',
	"Watching TV"         => '80537DE2A4674A76B3546DFD075F5EC6',
	"Meeting"             => 'F18AB52EDC57491D99DC6444502457AF',
	"Coffee"              => '1B78AE31FA0B4D3893D1997EEEAFB218',
	"Listening to music"  => '61BEE0DD8BDD475D8DEE5F4BAACF19A7',
	"Business"            => '488E14898ACA4A0882AA77CE7A165208',
	"Shooting"            => '107A9A1812324DA4B6CD0879DB780F09',
	"Having fun"          => '6F4930984F7C4AFFA27634A03BCEAEA7',
	"On the phone"        => '1292E5501B644F66B206B29AF378E48D',
	"Gaming"              => 'D4A611D08F014EC09223C5B6BEC6CCF0',
	"Studying"            => '609D52F8A29A49A6B2A02524C5E9D260',
	"Shopping"            => '63627337A03F49FF80E5F709CDE0A4EE',
	"Feeling sick"        => '1F7A4071BF3B4E60BC324C5787B04CF1',
	"Sleeping"            => '785E8C4840D34C65886F04CF3F3F43DF',
	"Surfing"             => 'A6ED557E6BF744D4A5D4D2E7D95CE81F',
	"Browsing"            => '12D07E3EF885489E8E97A72A6551E58D',
	"Working"             => 'BA74DB3E9E24434B87B62F6B8DFEE50F',
	"Typing"              => '634F6BD8ADD24AA1AAB9115BC26D05A1',
	"Picnic"              => '2CE0E4E57C6443709C3A7A1CE878A7DC',
	"Cooking"             => '101117C9A3B040F981AC49E159FBD5D4',
	"Smoking"             => '160C60BBDD4443F39140050F00E6C009',
	"I'm high"            => '6443C6AF22604517B58CD7DF8E290352',
	"On WC"               => '16F5B76FA9D240358CC5C084703C98FA',
	"To be or not to be"  => '631436ff3f8a40d0a5cb7b66e051b364',
	"Watching pro7 on TV" => 'b70867f538254327a1ffcf4cc1939797',
	"Love"                => 'ddcf0ea971954048a9c6413206d6f280',
	);

	/**
	 * Set default values
	 *
	 * @return WebIcqPro_SNAC
	 */
	protected function __construct()
	{
		$this->request_id = 0;
		$this->setMessageType();
		$this->setMessageCapabilities();
		$this->setUserAgent();
	}

	private function parseSnac($snac)
	{
		if (strlen($snac) > 10)
		{
			return unpack('ntype/nsubtype/nflag/Nrequest_id/a*data', $snac);
		}
		$this->error = 'Error: Broken SNAC can`t parse';
		return false;
	}

	protected function analizeSnac($snac)
	{
		$snac = $this->parseSnac($snac);
		if ($snac)
		{
			if (isset($this->snac_names[$snac['type']][$snac['subtype']]))
			{
				if (method_exists($this, $this->snac_names[$snac['type']][$snac['subtype']]))
				{
					$snac['callback'] = $this->snac_names[$snac['type']][$snac['subtype']];
				}
			}
		}
		return $snac;
	}

	/**
	 * Return SNAC header
	 *
	 * @param ineger $type
	 * @param integer $subtype
	 * @param integer $flag
	 * @return string binary
	 */
	private function __header($type, $subtype, $flag = 0)
	{
		return pack('nnnN', $type, $subtype, $flag, ++$this->request_id);
	}

	/**
	 * Pack ready CNAC
	 *
	 * @return string binary
	 */
	protected function ClientReady($args)
	{
		return $this->__header(0x01, 0x02).pack('n*', 0x0001, 0x0003, 0x0110, 0x028A, 0x0002, 0x0001, 0x0110, 0x028A, 0x0003, 0x0001, 0x0110, 0x028A, 0x0015, 0x0001, 0x0110, 0x028A, 0x0004, 0x0001, 0x0110, 0x028A, 0x0006, 0x0001, 0x0110, 0x028A, 0x0009, 0x0001, 0x0110, 0x028A, 0x000A, 0x0001, 0x0110, 0x028A);
	}

	protected function ServerFamilies($data)
	{
		$families = unpack('n*', $data);
		foreach ($this->snac_names as $family => $value)
		{
			if (!in_array($family, $families))
			{
				unset($this->snac_names[$family]);
			}
		}
		return true;
	}

	protected function ClientRates($args)
	{
		return $this->__header(0x01, 0x06);
	}

	protected function ServerRates($data)
	{
		$classes = unpack('n', $data);
		$data = substr($data, 2);
		for ($i = 0; $i < $classes; $i++)
		{
			if (strlen($data)>35)
			{
				$rate = unpack('nrate/Nwindow/Nclear/Nalert/Nlimit/Ndisconect/Ncurrent/Nmax/Ntime/cstate', $data);
				$this->rates[array_shift($rate)] = $rate;
				$data = substr($data, 35);
			}
			else
			{
				$this->error = 'Notice: Can`t get rates from server';
				return false;
			}
		}
		while (strlen($data) >= 4)
		{
			$group = unpack('nclass/nsise');
			$data = substr($data, 4);
			if (strlen($data) >= 4*$group['size'])
			{
				$this->rates_groups[$group['class']] = unpack(substr(str_repeat('N/', $group['size']), -1), $data);
				$data = substr($data, 4*$group['size']);
			}
			else
			{
				$this->error = 'Notice: Can`t get rates groups from server';
				return false;
			}
		}
		if (strlen($data))
		{
			$this->error = 'Notice: Can`t get rates/groups from server';
			return false;
		}
		return true;
	}

	protected function ClientRatesAck($args)
	{
		if (is_array($this->rates_groups) && count($this->rates_groups))
		{
			$snac = $this->__header(0x01, 0x08);
			foreach ($this->rates_groups as $group => $falilies)
			{
				$snac .= pack('n', $group);
			}
			return $snac;
		}
		$this->error = 'Error: Can`t create CNAC rates groups empty';
		return false;
	}

	protected function ClientRequestSelfInfo($args)
	{
		return $this->__header(0x01, 0x0E);
	}

	/**
	 * Requested online info response
	 *
	 * @todo implement this functionality
	 * @param string $data
	 * @return boolean
	 */
	protected function ServerResponseSelfInfo($data)
	{
		return true;
	}

	protected function ClientIdleTime($args)
	{
		return $this->__header(0x01, 0x11).pack('N', 1);
	}

	protected function ClientFamiliesVersions($args)
	{
		$snac = $this->__header(0x01, 0x17);
		foreach ($this->snac_names as $fname => $family)
		{
			$snac .= pack('n2', $fname, $family['version']);
		}
		return $snac;
	}

	protected function ServerFamiliesVersions($data)
	{
		$families = unpack('n*', $data);
		while (count($families)>1)
		{
			$falily = array_shift($families);
			$version = array_shift($families);
			$versions[$falily] = $version;
		}

		foreach ($this->snac_names as $fname => $family)
		{
			if (!isset($versions[$fname])) // todo: need to do something with families lower versions // || $versions[$fname] > $family['version']
			{
				unset($this->snac_names[$fname]);
			}
		}
		return true;
	}

	protected function ClientStatus($args)
	{
		extract($args);
		$snac = $this->__header(0x01, 0x1E);
		$snac .= $this->packTLV(0x06, ($this->substatuses[$substatus]<<16) + $this->statuses[$status], 'N');
		return $snac;
	}


	protected function ClientLocationRights($args)
	{
		return $this->__header(0x02, 0x02);
	}

	protected function ServerLocationRights($data)
	{
		return true;
	}

	protected function ClientLocationInfo($args)
	{
		return $this->__header(0x02, 0x04).$this->packTLV(0x05, implode($this->capabilities).$this->user_agent_capability[$this->agent], 'H*');
	}

	protected function ClientBuddylistRights($args)
	{
		return $this->__header(0x03, 0x02);
	}

	protected function ServerBuddylistRights($data)
	{
		return true;
	}

	protected function ClientIBCMRights()
	{
		return $this->__header(0x04, 0x04);
	}

	protected function ServerIBCMRights($data)
	{
		return true;
	}

	/**
	 * Pack message to SNAC
	 *
	 * @param string $uin
	 * @param string $message
	 * @return string binary
	 */
	protected function ClientIBCM($args)
	{
		extract($args);
		$uin_size     = strlen($uin);
		$message_size = strlen($message);

		$cookie = microtime();
		$snack = $this->__header(0x04, 0x06).pack('dnca*', $cookie, $this->ibcm_type, $uin_size, $uin);

		switch ($this->ibcm_type)
		{
			case 0x01:
				$tlv_data = pack('c2nc3n3a*', 0x05, 0x01, 0x01, 0x01, 0x01, 0x01, ($message_size+4), 0x03, 0x00, $message);
				$snack .= $this->packTLV(0x02, $tlv_data).$this->packTLV(0x03).$this->packTLV(0x06);
				break;
			case 0x02:
				$tlv_data = pack('ndH*n5n2v2d2nVn3dn3cvnva*H*', 0x00, $cookie, $this->ibcm_capabilities, 0x0A, 0x02, 0x01, 0x0F, 0x00, 0x2711, ($message_size+62), 0x1B, $this->protocol_version, 0x00, 0x00, 0x00, 0x03, $this->request_id, 0x0E, $this->request_id, 0x00, 0x00, 0x00, 0x01, 0x00, 0x00, 0x01, ($message_size+1), $message, '0000000000FFFFFF00');
				$snack .= $this->packTLV(0x05, $tlv_data).$this->packTLV(0x03);
				break;
			default:
				$this->error = 'Warning: Snac ClientIBCM unknown ibcm_type';
				$snack = false;
				break;
		}

		return $snack;
	}

	/**
	 * Unpack message from server
	 *
	 * @param string $data
	 * @return array
	 */
	protected function ServerIBCM($data)
	{
		if (strlen($data))
		{
			$return = array();
			$msg = unpack('dcookie/nchannel/cnamesize', $data);
			$data = substr($data, 11);

			$return['from'] = substr($data, 0, $msg['namesize']);
			$return['channel'] = $msg['channel'];
			$return['cookie'] = $msg['cookie'];

			$data = substr($data, $msg['namesize']);
			$msg = unpack('nwarnlevel/nTLVnumber', $data);

			$data = substr($data, 4);
			$tlvs = $this->splitTLVsToArray($data);
			foreach ($tlvs as $type => $data)
			{
				switch ($type)
				{
					case 2:
						if ($return['channel'] == 1)
						{
							$subtlvs = $this->splitTLVsToArray($data, true);
							if (isset($subtlvs[0x0101]))
							{
								$return['message'] = substr($subtlvs[0x0101], 4);
							}
						}
						break;
					case 5:
						if ($return['channel'] == 2)
						{
							$msg = unpack('ntype/dcookie/a*capability', $data);
							$return['type'] = $msg['type'];

							$subtlvs = $this->splitTLVsToArray(substr($data, 26));
							foreach ($subtlvs as $type => $data)
							{
								switch ($type)
								{
									case 0x00004:
										$return['external_ip'] = long2ip($data);
										break;
									case 0x2711:
										if (strlen($data) > 52)
										{
											$size = unpack('vsize', substr($data, 51, 2));
											$return['rtf'] = substr($data, 53, $size['size']);
											$return['message'] = RTF::Text($return['rtf']);
										}
										break;
								}
							}
						}
						break;
					case 6:
						$return['status']    = substr($data, 2);
						$return['substatus'] = substr($data, 0, 2);
						break;
				}
			}
			$this->writeFlap('ClientIBCMAck', $return);
			return $return;
		}
		return false;
	}

	protected function ClientIBCMAck($args)
	{
		if (is_array($args))
		{
			extract($args);
			$uin_size = strlen($from);
			$channel  = isset($channel) ? $channel : 0x00;
			$cookie   = isset($cookie) ? $cookie : 0x00;
			$reason   = isset($reason) ? $reason : (in_array($channel, array(0x01, 0x02)) ? 0x03 : 0x01);
			$message  = (isset($message) &&  $message != '') ? '' : $this->status_message;

			$snack = $this->__header(0x04, 0x0B).pack('dnca*n', $cookie, $channel, $uin_size, $from, $reason);

			switch ($channel)
			{
				case 0x02:
					$data = pack('vH*nH*cn', $this->protocol_version, '00000000000000000000000000000000', 0x00, $this->capability_flag, 0x00, 0x07);
					$message = pack('nH*va*H*', 0x07, '000000000000000000000000E80300000000', strlen($message)+1, $message, '0000000000FFFFFF00');
					$data .= pack('v', strlen($message)).$message;
					$snack .= pack('v', 0x1B).$data;
					break;
				default:
					$message_size = strlen($message);
					$snack .= pack('c2nc3n3a*', 0x05, 0x01, 0x01, 0x01, 0x01, 0x01, ($message_size+4), 0x03, 0x00, $message);
					break;
			}

			return $snack;
		}
		else
		{
			// todo: get answer from server.
		}
	}


	protected function ClientPrivicyRights()
	{
		return $this->__header(0x09, 0x02);
	}

	protected function ServerPrivicyRights($data)
	{
		return true;
	}

	protected function ClientSSIRights($args)
	{
		return $this->__header(0x13, 0x02);
	}

	protected function ServerSSIRights($data)
	{
		return true;
	}

	/**
	 * Check SSI state (time / number of items)
	 *
	 * @todo implement this functionality
	 * @param string $data
	 * @return boolean
	 */
	protected function ClientSSICheckout($args)
	{
		return $this->__header(0x13, 0x02).pack('Nn', time(), 0x00);
	}

	protected function ClientSSIActivate($args)
	{
		return $this->__header(0x13, 0x02);
	}

	protected function ServerSSIModificationDate($data)
	{
		return true;
	}

	protected function ClientMetaData($args)
	{
		extract($args);
		$ret = $this->__header(0x15, 0x02);
		switch ($type)
		{
			case 'offline': // 003C
			$ret .= $this->packTLV(0x01, pack('vVvv', 0x08, $uin, 0x3C, 0x02));
			break;
			case 'delete_offline': // 003E
			$ret .= $this->packTLV(0x01, pack('vVvv', 0x08, $uin, 0x3E, 0x00));
			break;
			default: // todo: 07D0
			//$ret .= $this->packTLV(0x01, pack('vVvv', 0x00, $uin, 0x07D0, 0x00));
			break;
		}
		return $ret;
	}

	protected function ServerMetaData($data)
	{
		if (strlen($data))
		{
			$return = array();
			$data = substr($data, 4);

			if (strlen($data) > 0)
			{
				$msg = unpack('vsize/Vmyuin/vtype', $data);
				$msg['data'] = substr($data, 10);
				switch ($msg['type'])
				{
					case 0x41: // Offline message
					$msg = unpack('Vfrom/vyear/Cmonth/Cday/Chour/Cminute/Cmsgtype/Cflag/nlength/a*message', $msg['data']);
					break;
					case 0x42: // End of offline messages
					$this->writeFlap('ClientMetaData', array('uin' => $msg['myuin'], 'type' => 'delete_offline'));
					$msg = true;
					break;
					default: // todo: 07DA
					//$ret .= $this->packTLV(0x01, pack('vVvv', 0x00, $uin, 0x07DA, 0x00));
					break;
				}
			}
			return $msg;
		}
		return false;
	}

	protected function ClientMd5Request($args)
	{
		extract($args);
		return $this->__header(0x17, 0x06).$this->packTLV(0x01, $uin).$this->packTLV(0x4B).$this->packTLV(0x5A);

	}

	protected function ServerMd5Response($data)
	{
		return substr($data, 2);
	}

	/*protected function ClientMd5Login($args)
	{
		extract($args);
		$password = pack('H*', md5($password));
		$password = pack('H*', md5($authkey.$password.'AOL Instant Messenger (SM)'));
		return $this->__header(0x17, 0x02).$this->packTLV(0x01, $uin).$this->packTLV(0x25, $password).$this->packTLV(0x4C, '').$this->packTLV(0x03, 'ICQBasic').$this->packTLV(0x16, 0x010A, 'n').$this->packTLV(0x17, 0x0014, 'n').$this->packTLV(0x18, 0x34, 'n').$this->packTLV(0x19, 0x00, 'n').$this->packTLV(0x1A, 0x0BB8, 'n').$this->packTLV(0x14, 0x0000043D, 'N').$this->packTLV(0x0F, 'en').$this->packTLV(0x0E, 'us');
	}*/

	
	protected function ClientMd5Login($args)
	{
		extract($args);
		$password = pack('H*', md5($password));
		$password = pack('H*', md5($authkey.$password.'AOL Instant Messenger (SM)'));
		return $this->__header(0x17, 0x02).
				$this->packTLV(0x01, $uin).
				$this->packTLV(0x25, $password).
				$this->packTLV(0x4C, '').
				$this->packTLV(0x03, 'ICQ Client').
				$this->packTLV(0x16, 0x010A, 'n').
				$this->packTLV(0x17, 0x0006, 'n').
				$this->packTLV(0x18, 0x00, 'n').
				$this->packTLV(0x19, 0x00, 'n').
				$this->packTLV(0x1A, 0x1B67, 'n').
				$this->packTLV(0x14, 0x00007535, 'N').
				$this->packTLV(0x0F, 'ru').
				$this->packTLV(0x0E, 'ru').
				$this->packTLV(0x94, 0x00, 'c');
	}	
	
	protected function ServerMd5LoginReply($data)
	{
		$tlv = $this->splitTLVsToArray($data);
		if (isset($tlv[0x05]) && isset($tlv[0x06]))
		{
			return array($tlv[0x05], $tlv[0x06]);
		}
		$this->error = 'Error: can`t parse server answer';
		if (isset($tlv[0x08]))
		{
			$error_no = unpack('n',$tlv[0x08]);
			if ($error_no && isset($this->login_errors[$error_no[1]])) {
				$this->error = $this->login_errors[$error_no[1]];
			}
		}
		return false;
	}

	/**
	 * Dump SNAC to file
	 *
	 * @param unknown_type $str
	 */
	private function dump($str)
	{
		$f = fopen('dump', 'a');
		fwrite($f, $str);
		fclose($f);
	}

	/**
	 * Set message capability
	 *
	 * @param string $value
	 * @return boolean
	 */
	protected function setMessageCapabilities($value = 'utf-8')
	{
		switch (strtolower($value))
		{
			case 'rtf':
				$this->ibcm_capabilities = '97B12751243C4334AD22D6ABF73F1492';
				break;
			case 'utf-8':
				$this->ibcm_capabilities = '094613494C7F11D18222444553540000';
				break;
			default:
				$this->error = 'Warning: MessageCapabilities: "'.$value.'" unknown';
				return false;
		}
		return true;
	}

	protected function setUserAgent($value = 'webicqpro')
	{
		$value = strtolower($value);
		if (isset($this->user_agent_capability[$value]))
		{
			$this->agent = $value;
			return true;
		}
		$this->error = 'Warning: UserAgent: "'.$value.'" is not valid user agent or has unknown capability';
		return false;
	}

	/**
	 * Set message type
	 *
	 * @param string $value
	 * @return boolean
	 */
	protected function setMessageType($value = 'plain_text')
	{
		switch (strtolower($value))
		{
			case 'plain_text':
				$this->ibcm_type = 0x01;
				break;
			case 'rtf':
				$this->ibcm_type = 0x02;
				break;
			case 'old_style':
				$this->ibcm_type = 0x04;
				break;
			default:
				$this->error = 'Warning: MessageType: "'.$value.'" unknown';
				return false;
		}
		return true;
	}
}

/**
 * Layer for FLAP data format
 * @access private
 */
class WebIcqPro_FLAP extends WebIcqPro_SNAC{

	protected $channel;
	private $sequence;
	private $body;
	private $info = array();

	protected function __construct()
	{
		parent::__construct();
		$this->sequence = rand(0x0000, 0x8000);
	}

	private function getSequence()
	{
		if (++$this->sequence > 0x8000)
		{
			$this->sequence = 0x00;
		}
		return $this->sequence;
	}

	protected function packFlap($body)
	{
		return pack('ccnn', 0x2A, $this->channel, $this->getSequence(), strlen($body)).$body;
	}

	protected function helloFlap($flap = false, $extra = '')
	{
		if ($flap)
		{
			if (isset($flap['data']) && strlen($flap['data']) == 4)
			{
				return unpack('N', $flap['data']);
			}
		}
		else
		{
			return $this->packFlap(pack('N', 0x01).$extra);
		}
		return false;
	}
}

/**
 * Class for simple work with socets
 * @access private
 */
class WebIcqPro_Socet extends WebIcqPro_FLAP
{
	protected $socet = false;
	private $server_url;
	private $server_port;
	private $timeout_second;
	private $timeout_msecond;

	protected function __construct()
	{
		parent::__construct();
		$this->setServerUrl();
		$this->setServerPort();
		$this->setTimeout(6,0);
	}

	protected function socetOpen()
	{
		$this->socet = fsockopen($this->server_url, $this->server_port, $erorno, $errormsg, ($this->timeout_second+$this->timeout_msecond/1000));
		if ($this->socet)
		{
			return true;
		}
		$this->error = 'Error: Cant establish connection to: '.$this->server_url.':'.$this->server_port."\n".$errormsg;
		return false;
	}

	protected function socetClose()
	{
		@fclose($this->socet);
		$this->socet = false;
	}

	protected function socetWrite($data)
	{
		if ($this->socet)
		{
			stream_set_timeout($this->socet, $this->timeout_second, $this->timeout_msecond);
			return fwrite($this->socet, $data);
		}
		$this->error = 'Error: Not connected';
		return false;
	}

	protected function writeFlap($name, $args = array())
	{
		if (method_exists($this, $name))
		{
			return $this->socetWrite($this->packFlap($this->$name($args)));
		}

		return false;
	}

	private function socetRead($size)
	{
		if ($this->socet)
		{
			stream_set_timeout($this->socet, $this->timeout_second, $this->timeout_msecond);
			$data = @fread($this->socet, $size);
			$socet_status = stream_get_meta_data($this->socet);
			if ($data && !$socet_status['timed_out'])
			{
				return $data;
			}
			if ($socet_status['eof'])
			{
				$this->socet = false;
				$this->error = 'Error: Server close connection';
			}
		}
		return false;
	}

	protected function readFlap($name = false)
	{
		$flap = $this->socetRead(6);
		if ($flap)
		{
			$flap = unpack('ccommand/cchanel/nsequence/nsize', $flap);
			if ($flap['chanel'] == 4)
			{
				$this->error = 'Notice: Server close connection';
				$this->socetClose();
				return false;
			}
			$flap['data'] = $this->socetRead($flap['size']);
			if ($flap['data'])
			{
				if ($name)
				{
					$snac = $this->analizeSnac($flap['data']);
					if (isset($snac['callback']) && $snac['callback'] == $name)
					{
						return $this->$name($snac['data']);
					}
					elseif(isset($snac['callback']))
					{
						$this->$snac['callback']($snac['data']);
						$this->error = 'Warning: Wrong server response "'.$name.'" expected but SNAC('.dechex($snac['type']).', '.dechex($snac['subtype']).') received';
					}
					return false;
				}
				return $flap;
			}
		}
		return false;
	}

	protected function readSocket()
	{
		$flap = $this->socetRead(6);
		if ($flap)
		{
			$flap = unpack('ccommand/cchanel/nsequence/nsize', $flap);
			if ($flap['chanel'] == 4)
			{
				$this->error = 'Notice: Server close connection';
				$this->socetClose();
				return false;
			}
			$flap['data'] = $this->socetRead($flap['size']);
			if ($flap['data'])
			{
				$snac = $this->analizeSnac($flap['data']);
				if (isset($snac['callback']))
				{
					return $this->$snac['callback']($snac['data']);
				}
			}
		}
		return false;
	}

	protected function setTimeout($second = 1, $msecond = 0)
	{
		$this->timeout_second = $second;
		$this->timeout_msecond = $msecond;
	}

	protected function setServerUrl($value = 'login.icq.com')
	{
		$this->server_url = $value;
		return true;
	}

	protected function setServerPort($value = 5190)
	{
		$this->server_port = $value;
		return true;
	}

	protected function setServer($value = 'login.icq.com:5190')
	{
		$server = explode(':', $value);
		if (count($server) == 2 && is_numeric($server[1]))
		{
			$this->server_url  = $server[0];
			$this->server_port = $server[1];
			return true;
		}
		$this->error = 'Error: Wrong server address format';
		return false;
	}
}

/**
 * Set of tools for simple and clear work with ICQ(OSCAR) protocol.
 *
 */
class WebIcqPro extends WebIcqPro_Socet {


	/**
	 * Constructor for WebIcqPro class
	 * @access internal
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Establish connection to ICQ server
	 * 
	 * Method initiate login sequence with MD5 based authorization.
	 * 
	 * @todo full client request/responses support
	 *
	 * @param string $uin
	 * @param string $pass
	 * @return boolean
	 */
	public function connect($uin, $pass)
	{
		if ($this->socet)
		{
			$this->error = 'Error: Connection already opened';
			return false;
		}
		if ($this->socetOpen())
		{
			$flap = $this->readFlap();
			$this->channel = 0x01;
			if ($this->helloFlap($flap))
			{
				$this->socetWrite($this->helloFlap());
				$uin = str_replace('-', '', $uin);
				$this->channel = 0x02;
				$this->writeFlap('ClientMd5Request', array('uin' => $uin));
				$authkey = $this->readFlap('ServerMd5Response');
				if ($authkey)
				{
					$this->writeFlap('ClientMd5Login', array('uin' => $uin, 'password' => $pass, 'authkey' => $authkey));
					$reconect = $this->readFlap('ServerMd5LoginReply');
					$this->disconnect();
					if ($reconect)
					{
						$this->setServer(array_shift($reconect));
						$cookie = array_shift($reconect);
						if ($this->socetOpen())
						{
							$flap = $this->readFlap();
							$this->channel = 0x01;
							if ($this->helloFlap($flap))
							{
								$this->socetWrite($this->helloFlap(false, $this->packTLV(0x06, $cookie)));
								$this->channel = 2;
								$this->readFlap('ServerFamilies');
								$this->writeFlap('ClientFamiliesVersions');
								$this->readFlap('ServerFamiliesVersions');
								$this->writeFlap('ClientRates');
								$this->readFlap();
								if (is_array($this->rates_groups) && count($this->rates_groups))
								{
									$this->writeFlap('ClientRatesAck');
								}
								$this->writeFlap('ClientSSIRights');
								$this->writeFlap('ClientSSICheckout'); // todo: nuber of items send
								$this->writeFlap('ClientLocationRights');
								$this->writeFlap('ClientBuddylistRights');
								$this->writeFlap('ClientIBCMRights');
								$this->writeFlap('ClientPrivicyRights');
								//								$this->ClientLimitations();
								$this->writeFlap('ClientLocationInfo');
								$this->writeFlap('ClientReady');
								return true;
							}
						}
					}
				}
				else
				{
					$this->readFlap('ServerMd5LoginReply');
				}
			}
		}
		return false;
	}

	/**
	 * Method activate Status Notifications
	 * 
	 * This method make possible to read Status Notifications for contacts in your contact list.
	 * 
	 * @deprecated Generate a traffic, but handler is not implemented yet!
	 * @todo make it useful. Let's class reads status notifications!
	 *
	 * @return boolean
	 */
	public function activateStatusNotifications()
	{
		return $this->writeFlap('ClientSSIActivate');
	}

	/**
	 * Method set status for client
	 * 
	 * Parameters are case insensitive. Try to set different substatuses for privicy and etc. purposes.
	 * You can set any icq status from the list:
	 * - STATUS_ONLINE
	 * - STATUS_AWAY
	 * - STATUS_DND
	 * - STATUS_NA
	 * - STATUS_OCCUPIED
	 * - STATUS_FREE4CHAT
	 * - STATUS_INVISIBLE
	 * 
	 * Also possible to set substutuses:
	 * - STATUS_WEBAWARE
	 * - STATUS_SHOWIP
	 * - STATUS_BIRTHDAY
	 * - STATUS_WEBFRONT
	 * - STATUS_DCDISABLED
	 * - STATUS_DCAUTH
	 * - STATUS_DCCONT
	 *
	 * @param string $status
	 * @param string $substatus
	 * @return boolean
	 */
	public function setStatus($status = 'STATUS_ONLINE', $substatus = 'STATUS_DCCONT', $message = '')
	{
		$this->status_message = $message;
		if (isset($this->statuses[$status]))
		{
			if (isset($this->substatuses[$substatus]))
			{
				return $this->writeFlap('ClientStatus', array('status' => $status, 'substatus' => $substatus,)) && $this->writeFlap('ClientIdleTime');
			}
			$this->error = 'setStatus: unknown substatus '.$substatus;
			return false;
		}
		$this->error = 'setStatus: unknown status '.$status;
		return false;
	}

	/**
	 * Method set X status for client
	 * 
	 *
	 * @param string $status
	 * @return boolean
	 */
	public function setXStatus($status = '')
	{
		if (isset($this->x_statuses[$status]))
		{
			$this->capabilities['xSatus'] = $this->x_statuses[$status];
		} elseif ($status == '') {
			unset($this->capabilities['xSatus']);
		} else {
			$this->error = 'setXStatus: unknown status '.$status;
			return false;
		}
		return $this->writeFlap('ClientLocationInfo');
	}

	/**
	 * Method return all available X Statuses
	 *
	 * @return array
	 */
	public function getXStatuses()
	{
		return array_keys($this->x_statuses);
	}

	/**
	 * Method indicate the connection status.
	 * 
	 * 
	 *
	 * @return boolean
	 */
	public function isConnected()
	{
		return $this->socet;
	}

	/**
	 * Close connection
	 *
	 */
	public function disconnect()
	{
		$this->channel = 0x04;
		$this->socetWrite($this->packFlap(''));
		$this->socetClose();
	}

	/**
	 * Activate the posibility to read offline messages.
	 * 
	 * After activation offline messages will be accessable like simple messages.
	 *
	 * @see readMessage
	 * @param string $uin
	 * @return boolean
	 */
	public function activateOfflineMessages($uin)
	{
		return $this->writeFlap('ClientMetaData', array('uin' => $uin, 'type' => 'offline'));
	}

	/**
	 * Read message from the server.
	 * 
	 * Return an associated array with different set of values of false if nathisn to read.
	 * Available sets of data:
	 * - from - sender UIN
	 * - message - the message
	 * - ... - can be ather information like status and etc.
	 *
	 * @return array
	 */
	public function readMessage()
	{
		return $this->readSocket();
	}

	/**
	 * Send message to the server.
	 * 
	 * Try to send message to the $uin.
	 *
	 * @todo check the delivery
	 * @param string $uin
	 * @param string $message
	 * @return boolean
	 */
	public function sendMessage($uin, $message)
	{
		return $this->writeFlap('ClientIBCM', array('uin' => $uin, 'message' => $message));
	}

	/**
	 * Sets additional options
	 * 
	 * Do not change this options if you dont know what it mean.
	 * 
	 * Usage: $icq->setOption('UserAgent', 'miranda');
	 * 
	 * Options available:
	 * MessageType - rtf, utf, old style(offline message)
	 * MessageCapabilities - utf, rtf
	 * UserAgent - miranda, sim, trillian, licq, kopete, micq, andrq, randq, mchat, jimm, macicq, icqlite
	 * Server - login.icq.com:5190
	 * ServerPort - 5190, 80, 8080
	 * Timeout - seconds before stop reading from socets.
	 * 
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return boolean
	 */
	public function setOption($name, $value)
	{
		$method = 'set'.$name;
		if (method_exists($this, $method))
		{
			return $this->$method($value);
		}
		$this->error = 'Warning: setOption name: "'.$name.'" unknown';
		return false;
	}
}
?>