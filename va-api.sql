DROP DATABASE  IF EXISTS pigeon_gateway;
CREATE DATABASE pigeon_gateway;
USE pigeon_gateway;
DROP TABLE IF EXISTS api_users;
CREATE TABLE `api_users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(250) DEFAULT NULL,
    `username` varchar(250) DEFAULT NULL,
    `password` varchar(250) DEFAULT NULL,
    `description` varchar(250) DEFAULT NULL,
    whitelisted_ips text default '[]',
    `permissions` text DEFAULT '["collections","disbursements"]',
    `created_` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `api_users` (`username`)
);
INSERT INTO api_users(name,username,password,description) VALUES('Pigeon App','dad85685-7fe4-4553-aa85-76fe84dd784d','$2y$10$4PcSQecIh6gBQIDdnNwCoucMMMbKJ4HeFzyp91HNXQVb7faya2Hw.','Development Purposes');
CREATE TABLE `api_user_preferences` (
     `id` bigint(20) NOT NULL AUTO_INCREMENT,
      `api_user_id` int(11) NOT NULL,
      `webHookUrl` varchar(255) DEFAULT NULL,
      `webHookSecret` varchar(255) DEFAULT NULL,
      `sendWebHookTransactionSucceeded` tinyint(1) NOT NULL DEFAULT 0,
      `sendWebHookTransactionFailed` tinyint(1) NOT NULL DEFAULT 0,
      `created_` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`)
);
INSERT INTO api_user_preferences(api_user_id, webHookUrl, webHookSecret, sendWebHookTransactionSucceeded, sendWebHookTransactionFailed) VALUES(1,'https://webhook.site/33779738-ba49-4df4-b804-2d52d262b600','%PIGEON#$',true,true);

DROP TABLE IF EXISTS pp_access_tokens;
CREATE TABLE `pp_access_tokens` (
     `id` bigint(20) NOT NULL AUTO_INCREMENT,
     `provider_id` int(11) NOT NULL,
     `product_id` int(11) NOT NULL,
     `access_token` text NOT NULL,
     `validity` int(11) NOT NULL,
     `updated_` timestamp NOT NULL DEFAULT current_timestamp(),
     `expires_` datetime NOT NULL,
     PRIMARY KEY (`id`),
     UNIQUE KEY `token_paymentProductId` (`provider_id`,`product_id`)
);
CREATE TABLE `transactions` (
     `id` bigint(11) NOT NULL AUTO_INCREMENT,
     `api_user_id` int(11) NOT NULL,
     `account` varchar(25) DEFAULT NULL,
     `amount` decimal(19,2) DEFAULT 0.00,
     `transactionReference` varchar(255) NOT NULL,
     `transactionNarrative` varchar(255) DEFAULT NULL,
     `privateTransactionReference` varchar(255) DEFAULT NULL,
     `transactionGeneralTransactionTypeId` int(11) NOT NULL DEFAULT 1,
     `transactionSpecificTransactionTypeId` int(11) NOT NULL DEFAULT 1,
     `transactionDesignationId` int(11) NOT NULL DEFAULT 1,
     `transactionStatus` int(11) NOT NULL DEFAULT 1,
     `transactionOriginatorIdentification` varchar(255) DEFAULT NULL,
     `transactionOriginatorDescription` varchar(255) DEFAULT NULL,
     `transactionOriginatorChannel` int(11) NOT NULL DEFAULT 1,
     `transactionMobileOperatorRef` varchar(255) DEFAULT NULL,
     `transactionMobileOperatorTransactionId` varchar(255) DEFAULT NULL,
     `transactionMobileOperatorResultCode` varchar(255) DEFAULT NULL,
     `transactionMobileOperatorResultMessage` text DEFAULT NULL,
     `transactionPaymentProviderId` int(11) NOT NULL,
     `initiationDate` timestamp NOT NULL DEFAULT current_timestamp(),
     `completionDate` timestamp NOT NULL DEFAULT current_timestamp(),
     `callbackRequested` tinyint(1) NOT NULL DEFAULT 0,
     `callbackProcessed` tinyint(1) NOT NULL DEFAULT 0,
     `created_` timestamp NOT NULL DEFAULT current_timestamp(),
     `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `transactionReference` (`transactionReference`)
);
CREATE TABLE `transactions_status` (
     `id` bigint(20) NOT NULL AUTO_INCREMENT,
     `name` varchar(100) DEFAULT NULL,
     `labelColor` varchar(100) DEFAULT NULL,
     PRIMARY KEY (`id`)
);
INSERT INTO `transactions_status` VALUES (1,'PENDING','yellow'),(2,'SUCCEEDED','green'),(3,'FAILED','red'),(4,'INDETERMINATE','orange');

CREATE TABLE `transactions_SpecificTransactionTypes` (
     `id` bigint(20) NOT NULL AUTO_INCREMENT,
     `name` varchar(100) DEFAULT NULL,
      PRIMARY KEY (`id`)
);
INSERT INTO `transactions_SpecificTransactionTypes` VALUES (1,'INBOUND_MSISDN_CREDIT'),(2,'INBOUND_ACCT_DEBIT'),(3,'INBOUND_ZPACCT_CREDIT'),(4,'OUTBOUND_ACCT_DEBIT'),(5,'INBOUND_CUSTOM_CREDIT'),(6,'OUTBOUND_CUSTOM_DEBIT'),(7,'OUTBOUND_AIRTIME_DEBIT'),(8,'OUTBOUND_DATA_DEBIT');

CREATE TABLE `transactions_OriginatorChannels` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id`)
);
INSERT INTO `transactions_OriginatorChannels` VALUES (1,'API'),(2,'WEB'),(3,'APP'),(4,'BACK_END_PROCESSOR');

CREATE TABLE `transactions_GeneralTransactionTypes` (
   `id` bigint(20) NOT NULL AUTO_INCREMENT,
   `name` varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id`)
);
INSERT INTO `transactions_GeneralTransactionTypes` VALUES (1,'CREDIT'),(2,'DEBIT');
CREATE TABLE `transactions_transactionDesignation` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
INSERT INTO `transactions_transactionDesignation` VALUES (1,'TRANSACTION'),(2,'CHARGES'),(3,'LIQUIDATION');

CREATE TABLE `payment_methods` (
   `id` bigint(11) NOT NULL AUTO_INCREMENT,
    `slug` char(50) NOT NULL,
    `name` varchar(100) NOT NULL,
    `active` tinyint(1) NOT NULL DEFAULT 0,
    `created_` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
);
INSERT INTO `payment_methods` (slug,name,active) VALUES ('mm','Mobile Money',1),('sp','SchoolPay',0);
CREATE TABLE `payment_provider_currencies` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `paymentProviderId` int(11) NOT NULL,
  `currencyName` varchar(255) NOT NULL,
  `currencyCode` varchar(100) NOT NULL,
  `created_` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
);
INSERT INTO `payment_provider_currencies` VALUES (1,1,'Ugandan Shillings (MTN Mobile Money)','UGX','2022-06-16 15:53:57','2022-07-31 16:13:49'),(2,2,'Ugandan Shillings (Airtel Money)','UGX','2022-06-16 15:53:57','2022-07-31 16:13:49'),(3,3,'Uganda Shillings','UGX','2022-06-16 15:53:57','2022-06-16 15:53:57');
CREATE TABLE `payment_providers` (
   `id` bigint(20) NOT NULL AUTO_INCREMENT,
   `providerFullName` varchar(255) NOT NULL,
   `providerServiceName` varchar(255) NOT NULL,
   `providerAccountCode` varchar(100) DEFAULT NULL,
   `paymentMethod` int(11) DEFAULT NULL,
   `created_` timestamp NOT NULL DEFAULT current_timestamp(),
   `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
   PRIMARY KEY (`id`)
);
INSERT INTO `payment_providers` (providerFullName,providerServiceName,providerAccountCode,paymentMethod) VALUES ('MTN Uganda Limited','MTN Mobile Money','MTN_UGANDA',1),('Airtel Uganda Limited','Airtel Money','AIRTEL_UGANDA',1),('SERVICE COPS','Uganda Shillings','SERVICE_COPS',2);
CREATE TABLE `scheduled_webhooks` (
   `id` bigint(20) NOT NULL AUTO_INCREMENT,
   `webhookAccountName` varchar(255) DEFAULT NULL,
   `webhookUrl` varchar(255) DEFAULT NULL,
   `webhookSecret` varchar(255) NOT NULL,
   `webhookPayload` text DEFAULT NULL,
   `processed` tinyint(1) NOT NULL DEFAULT 0,
   `created_` timestamp NOT NULL DEFAULT current_timestamp(),
   `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
   PRIMARY KEY (`id`),
   UNIQUE KEY `webhookSecret` (`webhookSecret`)
);
CREATE TABLE `back_office_login_otp` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
   `otp` int(11) NOT NULL,
   `user_id` int(11) NOT NULL,
   `valid` tinyint(1) NOT NULL DEFAULT 1,
   `requested_` timestamp NOT NULL DEFAULT current_timestamp(),
   `expires_` timestamp NOT NULL DEFAULT current_timestamp(),
   PRIMARY KEY (`id`),
   UNIQUE KEY `otp` (`otp`),
   UNIQUE KEY `user_id` (`user_id`),
   UNIQUE KEY `back_office_login_otp_User_id` (`otp`,`user_id`)
);
CREATE TABLE `back_office_password_resets` (
   `id` int(11) NOT NULL AUTO_INCREMENT,
   `reset_key` varchar(100) NOT NULL,
   `reset_token` int(11) NOT NULL,
   `user_id` int(11) DEFAULT NULL,
   `date_requested` timestamp NOT NULL DEFAULT current_timestamp(),
   PRIMARY KEY (`id`),
   UNIQUE KEY `reset_key` (`reset_key`)
);
CREATE TABLE `back_office_scheduled_email_tasks` (
   `id` bigint(20) NOT NULL AUTO_INCREMENT,
   `type` int(11) NOT NULL DEFAULT 1,
   `recipient` varchar(255) NOT NULL,
   `message` text DEFAULT NULL,
   `processed` tinyint(1) NOT NULL DEFAULT 0,
   `created_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
   `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
   PRIMARY KEY (`id`)
);
CREATE TABLE `back_office_user_roles` (
   `id` bigint(20) NOT NULL AUTO_INCREMENT,
   `name` varchar(255) NOT NULL,
   `type` int(11) NOT NULL DEFAULT 1,
   `description` text DEFAULT NULL,
   `permissions` text DEFAULT NULL,
   `created_` timestamp NOT NULL DEFAULT current_timestamp(),
   `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
   PRIMARY KEY (`id`)
);
INSERT INTO `back_office_user_roles` VALUES (1,'Administrator',1,'This role grants users the permissions to manage everything on the dashboard','[1,2,3]','2022-04-28 13:56:19','2022-04-28 13:56:19'),(2,'Operations',2,'This role grants users the permissions to manage everything on the dashboard except to move money out','[1,2,3]','2022-04-28 13:56:19','2022-04-28 13:56:19'),(3,'Developer Support',3,'This role grants users the permissions necessary for providing technical support and working with APIs','[1,2,3]','2022-04-28 13:56:19','2022-04-28 13:56:19'),(4,'Customer Support',4,'This is best for staff that perform actions like refunds, disputes managements.','[1,2,3]','2022-04-28 13:56:19','2022-04-28 13:56:19');
CREATE TABLE `back_office_users` (
   `id` bigint(20) NOT NULL AUTO_INCREMENT,
   `role_id` int(11) NOT NULL DEFAULT 1,
   `title` char(6) DEFAULT NULL,
   `email` varchar(200) NOT NULL,
   `password` text DEFAULT NULL,
   `fname` varchar(255) NOT NULL,
   `lname` varchar(255) NOT NULL,
   `otherNames` varchar(255) NOT NULL,
   `phone` varchar(100) NOT NULL,
   `email_verified` int(11) NOT NULL DEFAULT 0,
   `email_verification_hash` text DEFAULT NULL,
   `sendLoginAlerts` int(11) DEFAULT 1,
   `last_login` varchar(255) DEFAULT NULL,
   PRIMARY KEY (`id`),
   UNIQUE KEY `email` (`email`)
);
INSERT INTO `back_office_users` VALUES (1,1,NULL,'mariano.o@hotmail.com','$2y$10$0EwOkXAl8X0JK7z2xYe83uiJSaumjJH29968p5T/t5IHS1dMOrEim','Denis','Ojok','','256775203801',1,NULL,1,'2023-05-31 09:13:17');
CREATE TABLE `user_roles` (
    `id` bigint(20) NOT NULL AUTO_INCREMENT,
     `name` varchar(255) NOT NULL,
     `type` int(11) NOT NULL DEFAULT 1,
     `description` text DEFAULT NULL,
     `permissions` text DEFAULT NULL,
     `created_` timestamp NOT NULL DEFAULT current_timestamp(),
     `updated_` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
     PRIMARY KEY (`id`)
);
INSERT INTO `user_roles` (`id`, `name`, `type`, `description`, `permissions`) VALUES
            (1, 'Administrator', 1, 'This role grants users the permissions to manage everything on the dashboard', '[1,2,3]'),
            (2, 'Operations', 2, 'This role grants users the permissions to manage everything on the dashboard except to move money out', '[2,3]'),
            (3, 'Customer Support', 3, 'This is best for staff that perform actions like refunds, disputes managements.', '[3]');
