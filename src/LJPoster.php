<?php
// Copyright 2017 Sergey Lebedev
// Licensed under the Apache License, Version 2.0

namespace LJPoster;

require_once __DIR__.'/../vendor/autoload.php';

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;
use PhpXmlRpc\Encoder;

/**
 * a simple class for working with LiveJournal posts through API
 */
class LJPoster
{
    const HOST = 'www.livejournal.com';
    const API_URL = '/interface/xmlrpc';
    const API_METHOD_NAME_PREFIX = 'LJ.XMLRPC.';

    const API_V0 = 0;
    const API_V1 = 1;       // Makes LiveJournal API expect UTF-8 text instead of ISO-8859-1

    // supported LiveJournal API methods
    const API_METHOD_GET_CHALLENGE = 'getchallenge';

    const API_METHOD_CREATE_POST = 'postevent';
    const API_METHOD_EDIT_POST = 'editevent';
    const API_METHOD_GET_POSTS = 'getevents';

    // values used by LiveJournal API
    const POST_ACCESS_PUBLIC = "public";
    const POST_ACCESS_PRIVATE = "private";
    const POST_ACCESS_USEMASK = "usemask";

    const SELECT_TYPE_ONE_POST = 'one';
    const SELECT_TYPE_POST_FOR_A_DAY = 'day';
    const SELECT_TYPE_LASTN_POSTS = 'lastn';
    const SELECT_TYPE_SYNCITEMS_POSTS = 'syncitems';

    const LINEENDINGS_UNIX = 'unix';
    const LINEENDINGS_PC = 'pc';
    const LINEENDINGS_MAC = 'mac';

    const RETURN_LINEENDINGS_UNIX = self::LINEENDINGS_UNIX;
    const RETURN_LINEENDINGS_PC = self::LINEENDINGS_PC;
    const RETURN_LINEENDINGS_MAC = self::LINEENDINGS_MAC;
    const RETURN_LINEENDINGS_SPACE = 'space'; // lines ends with spaces
    const RETURN_LINEENDINGS_DOT = 'dot';   // lines ends with '...'

    const MOST_RECENT_POST_ID = -1;

    protected $login;
    protected $challenge;
    protected $authResponse;
    protected $XMLRPCClient;

    protected $lineEndingsType = self::LINEENDINGS_UNIX;
    protected $returnLineEndingsType = self::RETURN_LINEENDINGS_UNIX;

    /**
     * @param string $login
     * @param string $password
     */
    public function __construct($login, $password)
    {
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * Creates new post
     *
     * @param string $subject
     * @param string $content
     * @param DateTime $datetime
     * @param array $tags
     * @param array $addOptions
     * @param string $accessLevel
     * @return array with new post item info
     */
    public function createPost($subject, $content, \DateTime $datetime = null, array $tags = [], array $addOptions = [], $accessLevel = self::POST_ACCESS_PUBLIC)
    {
        $params = [];
        $this->setDateParams($params, $datetime, true);
        $this->setTimeParams($params, $datetime);

        $params['event'] = $content;
        $params['subject'] = $subject;

        $params['security'] = $accessLevel;

//        $params['props'] = [];
//        if (!empty($tags)) {
//            $params['props']['taglist'] = implode(',', $tags);
//        }
//
//        if (!empty($addOptions)) {
//            $params['props'] = array_merge($params['props'], $addOptions);
//        }

        $response = $this->doAPICall(self::API_METHOD_CREATE_POST, $params);
        return $response;
    }

    /**
     * Edits existing post
     *
     * @param int $itemId
     * @param string $subject
     * @param string $content
     * @param DateTime $datetime
     * @param array $tags
     * @param array $addOptions
     * @param string $accessLevel
     * @return array with new post item info
     */
    public function editPost($itemId, $subject, $content, \DateTime $datetime = null, array $tags = [], array $addOptions = [], $accessLevel = self::POST_ACCESS_PUBLIC)
    {
        $params = [];
        $params['itemid'] = $itemId;
        $this->setDateParams($params, $datetime, true);
        $this->setTimeParams($params, $datetime);

        $params['event'] = $content;
        $params['subject'] = $subject;

        $params['security'] = $accessLevel;

        $params['props'] = [];
        if (!empty($tags)) {
            $params['props']['taglist'] = implode(',', $tags);
        }

        if (!empty($addOptions)) {
            $params['props'] = array_merge($params['props'], $addOptions);
        }

        $response = $this->doAPICall(self::API_METHOD_EDIT_POST, $params);
        return $response;
    }

    /**
     * Deletes existing post
     *
     * @param int $itemId
     */
    public function deletePost($itemId)
    {
        $params['itemid'] = $itemId;
        $response = $this->doAPICall(self::API_METHOD_EDIT_POST, $params);
        return $response;
    }


    /**
     * Gets post by its id
     *
     * @param int $itemId
     * @return array
     */
    public function getPostById($itemId = self::MOST_RECENT_POST_ID)
    {
        $params['selecttype'] = self::SELECT_TYPE_ONE_POST;
        $params['itemid'] = $itemId;
        return $this->doAPICall(self::API_METHOD_GET_POSTS, $params);
    }

    /**
     * Gets all posts for a given date
     *
     * @param \DateTime $date
     * @return array
     */
    public function getPostsForDate(\DateTime $date = null)
    {
        $params = [];
        $this->setDateParams($params, $date);
        $params['selecttype'] = self::SELECT_TYPE_POST_FOR_A_DAY;
        return $this->doAPICall(self::API_METHOD_GET_POSTS, $params);
    }

    /**
     * Gets given number of last posts
     * if $dateTo is set then gets given number of last posts before that date
     *
     * @param int $postsNum
     * @param \DateTime $dateTo
     * @return type
     */
    public function getLastNPosts($postsNum = 1, \DateTime $dateTo = null)
    {
        $params = [];

        if (isset($dateTo)) {
            $params['beforedate'] = $dateTo->format('Y-m-d H:i:s');
        }

        $params['selecttype'] = self::SELECT_TYPE_LASTN_POSTS;
        $params['howmany'] = $postsNum;
        return $this->doAPICall(self::API_METHOD_GET_POSTS, $params);
    }

    /**
     * gets API challenge value and authResponse
     * @param string $password
     */
    protected function getChallenge($password)
    {
        $response = $this->doXMLRPCCall(self::API_METHOD_GET_CHALLENGE);
        $this->challenge = $response['challenge'];
        $this->authResponse = md5($this->challenge . md5($password));
    }

    /**
     * Sets date relating params for API call
     * if we are preparing params for PostEvent oe EditEvent API method then 'month' param should be called 'mon'
     *
     * @param array $params
     * @param \DateTime $datetime
     * @param bool $setMonthParamAsMon
     */
    protected function setDateParams(&$params, \DateTime $datetime = null, $setMonthParamAsMon = false)
    {
        if (!isset($datetime)) {
            $datetime = new \DateTime();
        }
        $params['year'] = $datetime->format('Y');
        $params['day'] = $datetime->format('d');

        if ($setMonthParamAsMon) {
            $params['mon'] = $datetime->format('m');
        } else {
            $params['month'] = $datetime->format('m');
        }
    }

    /**
     * Sets time relating params for API call
     *
     * @param array $params
     * @param \DateTime $datetime
     */
    protected function setTimeParams(&$params, \DateTime $datetime = null)
    {
        if (!isset($datetime)) {
            $datetime = new \DateTime();
        }
        $params['hour'] = $datetime->format('G');
        $params['min'] = $datetime->format('i');
    }

    /**
     * Sets line endings type used when posting texts to API
     *
     * @param string $lineEndingsType
     */
    public function setLineEndingsType($lineEndingsType = self::LINEENDINGS_UNIX)
    {
        $this->lineEndingsType = $lineEndingsType;
    }

    /**
     * Sets line endings type used when getting texts from API
     *
     * @param string $lineEndingsType
     */
    public function setReturnLineEndings($lineEndingsType = self::RETURN_LINEENDINGS_UNIX)
    {
        $this->returnLineEndingsType = $lineEndingsType;
    }


    /**
     * makes API call
     *
     * @param string $APIMethod
     * @param array $params
     * @return array API response
     */
    protected function doAPICall($APIMethod, array $params = [])
    {
        $this->getChallenge($this->password);
        $params['username'] = $this->login;
        $params['auth_method'] = 'challenge';
        $params['auth_challenge'] = $this->challenge;
        $params['auth_response'] = $this->authResponse;

        $params['ver'] = self::API_V1;

        return $this->doXMLRPCCall($APIMethod, $params);
    }

    /**
     * API call - XML-RPC low level stuff
     *
     * @param string $APIMethod
     * @param mixed $params
     * @return array API response
     * @throws Exception
     */
    protected function doXMLRPCCall($APIMethod, array $params = [])
    {
        if (!isset($this->XMLRPCClient)) {
            $this->XMLRPCClient = new Client(self::API_URL, self::HOST);
            // explicitly set accept encoding to plaintext because I had hard time debugging Perl/PHP deflate incompatibiltiy issues
            $this->XMLRPCClient->accepted_compression = 'identity';
        }

        $XMLRPCValue = (new Encoder())->encode($params);
        $requestParams = [new Value($XMLRPCValue, 'struct')];

        $request = new Request(self::API_METHOD_NAME_PREFIX . $APIMethod, $requestParams);
        $response = $this->XMLRPCClient->send($request);

        if (!$response || $response->faultCode() !== 0) {
            $errorCode = $response ? $response->faultCode() : '?';
            $errorMsg = $response ? $response->faultString() : '?';
            throw new \Exception('Error ' . $errorCode . ' : ' . $errorMsg);
        }

        return (new Encoder())->decode($response->value());
    }

}
