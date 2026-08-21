<?php

declare(strict_types=1);

use App\Domain\AuditLog\Entity\AuditLog;
use Doctrine\ORM\Mapping\ClassMetadata;

/* @var ClassMetadata<AuditLog> $metadata */
$metadata->setPrimaryTable(['name' => 'audit_logs']);

$metadata->mapField(['fieldName' => 'id', 'type' => 'string', 'id' => true, 'length' => 36]);
$metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);

$metadata->mapField(['fieldName' => 'actorId',      'type' => 'string',             'nullable' => true, 'length' => 36,  'columnName' => 'actor_id']);
$metadata->mapField(['fieldName' => 'actorEmail',   'type' => 'string',             'nullable' => true, 'length' => 255, 'columnName' => 'actor_email']);
$metadata->mapField(['fieldName' => 'action',       'type' => 'string',             'length' => 100]);
$metadata->mapField(['fieldName' => 'resourceType', 'type' => 'string',             'length' => 100,    'columnName' => 'resource_type']);
$metadata->mapField(['fieldName' => 'resourceId',   'type' => 'string',             'nullable' => true, 'length' => 255, 'columnName' => 'resource_id']);
$metadata->mapField(['fieldName' => 'metadata',     'type' => 'json']);
$metadata->mapField(['fieldName' => 'ipAddress',    'type' => 'string',             'nullable' => true, 'length' => 45,  'columnName' => 'ip_address']);
$metadata->mapField(['fieldName' => 'previousHash', 'type' => 'string',             'length' => 64,     'columnName' => 'previous_hash']);
$metadata->mapField(['fieldName' => 'hash',         'type' => 'string',             'length' => 64,     'unique' => true]);
$metadata->mapField(['fieldName' => 'createdAt',    'type' => 'datetime_immutable', 'columnName' => 'created_at']);
