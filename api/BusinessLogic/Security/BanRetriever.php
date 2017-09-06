<?php

namespace BusinessLogic\Security;


use DataAccess\Security\BanGateway;

class BanRetriever {
    /**
     * @var BanGateway
     */
    private $banGateway;

    function __construct($banGateway) {
        $this->banGateway = $banGateway;
    }

    /**
     * @param $email
     * @param $heskSettings
     * @return bool
     */
    function isEmailBanned($email, $heskSettings) {

        $bannedEmails = $this->banGateway->getEmailBans($heskSettings);

        foreach ($bannedEmails as $bannedEmail) {
            if ($bannedEmail->email === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $ip int the IP address, converted beforehand using ip2long()
     * @param $heskSettings
     * @return bool
     */
    function isIpAddressBanned($ip, $heskSettings) {
        $bannedIps = $this->banGateway->getIpBans($heskSettings);

        foreach ($bannedIps as $bannedIp) {
            if ($bannedIp->ipFrom <= $ip && $bannedIp->ipTo >= $ip) {
                return true;
            }
        }

        return false;
    }
}