<?php
/**
 * Created by PhpStorm.
 * User: cokoch
 * Date: 2/20/2017
 * Time: 12:40 PM
 */

namespace BusinessLogic\Tickets;


use DataAccess\Tickets\VerifiedEmailGateway;

class VerifiedEmailChecker {
    /**
     * @var $verifiedEmailGateway VerifiedEmailGateway
     */
    private $verifiedEmailGateway;

    function __construct($verifiedEmailGateway) {
        $this->verifiedEmailGateway = $verifiedEmailGateway;
    }

    function isEmailVerified($emailAddress, $heskSettings) {
        return $this->verifiedEmailGateway->isEmailVerified($emailAddress, $heskSettings);
    }
}