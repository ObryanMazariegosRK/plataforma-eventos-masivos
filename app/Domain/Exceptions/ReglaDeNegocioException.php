<?php
namespace App\Domain\Exceptions;

use DomainException;

/**
 * Base de las excepciones de negocio con significado propio.
 * El dominio no conoce HTTP: el código de estado de cada una
 * se asigna en un solo lugar, bootstrap/app.php (withExceptions).
 */
abstract class ReglaDeNegocioException extends DomainException {}
