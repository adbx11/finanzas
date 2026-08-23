-- MySQL dump 10.13  Distrib 8.0.34, for Win64 (x86_64)
--
-- Host: 192.168.10.156    Database: finanzas
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `C3P0TestTable`
--

DROP TABLE IF EXISTS `C3P0TestTable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `C3P0TestTable` (
  `a` char(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alicuotas_iva`
--

DROP TABLE IF EXISTS `alicuotas_iva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alicuotas_iva` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_afip` int DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `alicuota` decimal(40,20) DEFAULT '0.00000000000000000000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asiento_items`
--

DROP TABLE IF EXISTS `asiento_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asiento_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_asiento` int DEFAULT NULL,
  `id_cuenta` int DEFAULT NULL,
  `id_moneda` int DEFAULT NULL,
  `descripcion` varchar(512) DEFAULT NULL,
  `comentarios` varchar(2048) DEFAULT NULL,
  `debe` decimal(40,20) DEFAULT NULL,
  `haber` decimal(40,20) DEFAULT NULL,
  `debe_origen` decimal(40,20) DEFAULT NULL,
  `haber_origen` decimal(40,20) DEFAULT NULL,
  `cotizacion` decimal(40,20) DEFAULT NULL,
  `cuota` int DEFAULT NULL,
  `unidades` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_asiento` (`id_asiento`),
  KEY `id_cuenta` (`id_cuenta`)
) ENGINE=InnoDB AUTO_INCREMENT=74387 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asientos`
--

DROP TABLE IF EXISTS `asientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_origen` int DEFAULT NULL,
  `descripcion` varchar(512) DEFAULT NULL,
  `comentarios` varchar(2048) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `fecha_confirmacion` datetime DEFAULT NULL,
  `ejercicio` int DEFAULT NULL,
  `cuota` int DEFAULT NULL,
  `confirmado` tinyint(1) DEFAULT NULL,
  `id_movimiento` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_origen` (`id_origen`)
) ENGINE=InnoDB AUTO_INCREMENT=36583 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ciudad`
--

DROP TABLE IF EXISTS `ciudad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ciudad` (
  `id_ciudad` int NOT NULL AUTO_INCREMENT,
  `id_pais` int DEFAULT NULL,
  `id_provincia` int DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `habilitada` tinyint(1) DEFAULT NULL,
  `cantidad_items` int DEFAULT NULL,
  PRIMARY KEY (`id_ciudad`),
  KEY `k_provincia` (`id_provincia`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `coin_transactions`
--

DROP TABLE IF EXISTS `coin_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `coin_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coin` varchar(255) DEFAULT NULL,
  `wallet` varchar(255) DEFAULT NULL,
  `quantity` varchar(255) DEFAULT NULL,
  `ts` datetime DEFAULT NULL,
  `price_btc` decimal(40,20) DEFAULT NULL,
  `price_usd` decimal(40,20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coin` (`coin`)
) ENGINE=InnoDB AUTO_INCREMENT=223 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `condiciones_iva`
--

DROP TABLE IF EXISTS `condiciones_iva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `condiciones_iva` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_afip` int DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion` (
  `id_configuracion` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(255) DEFAULT NULL,
  `valor` varchar(255) DEFAULT NULL,
  `tipo` varchar(255) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `orden` int DEFAULT NULL,
  `grupo_orden` int DEFAULT NULL,
  `grupo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_configuracion`),
  UNIQUE KEY `configuracion_unique` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cotizaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `id_moneda` int DEFAULT NULL,
  `compra` decimal(40,20) DEFAULT NULL,
  `venta` decimal(40,20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_moneda` (`id_moneda`)
) ENGINE=InnoDB AUTO_INCREMENT=63398 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cuentas`
--

DROP TABLE IF EXISTS `cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuentas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_superior` int DEFAULT NULL,
  `id_moneda` int DEFAULT NULL,
  `codigo` varchar(32) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `tipo_estado` varchar(16) DEFAULT NULL,
  `tipo_cuenta` varchar(16) DEFAULT NULL,
  `nivel` int DEFAULT NULL,
  `imputable` tinyint(1) DEFAULT NULL,
  `clase` varchar(24) DEFAULT NULL,
  `habilitada` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `id_moneda` (`id_moneda`),
  KEY `id_superior` (`id_superior`)
) ENGINE=InnoDB AUTO_INCREMENT=154 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cuits_emisores`
--

DROP TABLE IF EXISTS `cuits_emisores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cuits_emisores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_tipo_doc` int DEFAULT NULL,
  `id_condicion_iva` int DEFAULT NULL,
  `doc_numero` varchar(64) DEFAULT NULL,
  `razon_social` varchar(255) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `limite_anual` decimal(40,20) DEFAULT '0.00000000000000000000',
  `fe_keystore_file` varchar(255) DEFAULT NULL,
  `fe_keystore_signer` varchar(255) DEFAULT NULL,
  `fe_keystore_pass` varchar(255) DEFAULT NULL,
  `inicio_actividades` date DEFAULT NULL,
  `cbu_informada` varchar(255) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `numero_ingresos_brutos` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_store_error`
--

DROP TABLE IF EXISTS `email_store_error`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_store_error` (
  `id_email_store_error` int NOT NULL AUTO_INCREMENT,
  `m_date` datetime DEFAULT NULL,
  `m_from` varchar(255) DEFAULT NULL,
  `m_to` text,
  `m_subject` varchar(255) DEFAULT NULL,
  `m_message` longblob,
  `m_retry` int DEFAULT NULL,
  `m_last_retry` datetime DEFAULT NULL,
  `m_next_retry` datetime DEFAULT NULL,
  `m_error` varchar(255) DEFAULT NULL,
  `ws_id` int DEFAULT NULL,
  `ws_tform` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `baja` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_email_store_error`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_store_sent`
--

DROP TABLE IF EXISTS `email_store_sent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_store_sent` (
  `id_email_store_sent` int NOT NULL AUTO_INCREMENT,
  `m_date` datetime DEFAULT NULL,
  `m_from` varchar(255) DEFAULT NULL,
  `m_to` text,
  `m_subject` varchar(255) DEFAULT NULL,
  `m_message` longblob,
  `m_retry` int DEFAULT NULL,
  `m_last_retry` datetime DEFAULT NULL,
  `m_next_retry` datetime DEFAULT NULL,
  `m_error` varchar(255) DEFAULT NULL,
  `ws_id` int DEFAULT NULL,
  `ws_tform` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `baja` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_email_store_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `factura_items`
--

DROP TABLE IF EXISTS `factura_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `factura_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_factura` int DEFAULT NULL,
  `id_alicuota_iva` int DEFAULT NULL,
  `cantidad` int DEFAULT NULL,
  `descripcion` text,
  `precio_unitario` decimal(40,20) DEFAULT '0.00000000000000000000',
  `precio_total` decimal(40,20) DEFAULT '0.00000000000000000000',
  `iva` decimal(40,20) DEFAULT '0.00000000000000000000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `facturas`
--

DROP TABLE IF EXISTS `facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `facturas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `id_origen` int DEFAULT NULL,
  `tipo_origen` varchar(255) DEFAULT NULL,
  `id_tipo_comprobante` int DEFAULT NULL,
  `id_cuit_emisor` int DEFAULT NULL,
  `punto_de_venta` int DEFAULT NULL,
  `numero` int DEFAULT NULL,
  `numero_completo` varchar(16) DEFAULT NULL,
  `cuit_emisor` varchar(64) DEFAULT NULL,
  `razon_social_emisor` varchar(255) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `domicilio` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `id_tipo_doc` int DEFAULT NULL,
  `doc_numero` varchar(64) DEFAULT NULL,
  `razon_social` varchar(255) DEFAULT NULL,
  `id_condicion_iva` int DEFAULT NULL,
  `comentarios` text,
  `importe_gravado` decimal(40,20) DEFAULT '0.00000000000000000000',
  `importe_no_gravado` decimal(40,20) DEFAULT '0.00000000000000000000',
  `importe_total` decimal(40,20) DEFAULT '0.00000000000000000000',
  `importe_exento` decimal(40,20) DEFAULT '0.00000000000000000000',
  `importe_iva` decimal(40,20) DEFAULT '0.00000000000000000000',
  `importe_otros_impuestos` decimal(40,20) DEFAULT '0.00000000000000000000',
  `tipo_cambio` decimal(40,20) DEFAULT '0.00000000000000000000',
  `moneda` varchar(8) DEFAULT NULL,
  `exportacion` tinyint(1) DEFAULT '0',
  `fe_fecha_cae` date DEFAULT NULL,
  `fe_cae` varchar(255) DEFAULT NULL,
  `fe_motivo` text,
  `fe_observaciones` text,
  `fe_id_solicitud` int DEFAULT NULL,
  `fe_reprocesar` tinyint(1) DEFAULT '0',
  `condicion_venta` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fci_cotizaciones`
--

DROP TABLE IF EXISTS `fci_cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fci_cotizaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `id_cuenta_fondo` int DEFAULT NULL,
  `cotizacion` decimal(40,20) DEFAULT NULL,
  `id_asiento` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cuenta_fondo` (`id_cuenta_fondo`)
) ENGINE=InnoDB AUTO_INCREMENT=1036 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fechas`
--

DROP TABLE IF EXISTS `fechas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fechas` (
  `fecha` date NOT NULL DEFAULT '0000-00-00',
  PRIMARY KEY (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fechas_anios`
--

DROP TABLE IF EXISTS `fechas_anios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fechas_anios` (
  `anio` varchar(4) NOT NULL DEFAULT '',
  PRIMARY KEY (`anio`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fechas_meses`
--

DROP TABLE IF EXISTS `fechas_meses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fechas_meses` (
  `mes` varchar(7) NOT NULL DEFAULT '',
  PRIMARY KEY (`mes`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `image`
--

DROP TABLE IF EXISTS `image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `image` (
  `id_image` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `size` int DEFAULT NULL,
  `type` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `width` int DEFAULT NULL,
  `temp` int DEFAULT NULL,
  `is_thumb` int DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  `id_caption` int DEFAULT NULL,
  `image_fk` int DEFAULT NULL,
  PRIMARY KEY (`id_image`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ingresos`
--

DROP TABLE IF EXISTS `ingresos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingresos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `id_cuenta_destino` int DEFAULT NULL,
  `id_cuenta_concepto` int DEFAULT NULL,
  `id_moneda` int DEFAULT NULL,
  `id_asiento` int DEFAULT NULL,
  `importe` decimal(40,20) DEFAULT NULL,
  `cotizacion` decimal(40,20) DEFAULT NULL,
  `comentarios` varchar(255) DEFAULT NULL,
  `id_origen` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cuenta_concepto` (`id_cuenta_concepto`),
  KEY `id_cuenta_destino` (`id_cuenta_destino`)
) ENGINE=InnoDB AUTO_INCREMENT=1840 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mining_log`
--

DROP TABLE IF EXISTS `mining_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mining_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coin` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `ts` datetime DEFAULT NULL,
  `hashrate` decimal(40,20) DEFAULT NULL,
  `hashrate_h1` decimal(40,20) DEFAULT NULL,
  `hashrate_h3` decimal(40,20) DEFAULT NULL,
  `hashrate_h6` decimal(40,20) DEFAULT NULL,
  `hashrate_h12` decimal(40,20) DEFAULT NULL,
  `hashrate_h24` decimal(40,20) DEFAULT NULL,
  `balance` decimal(40,20) DEFAULT NULL,
  `difficulty` varchar(255) DEFAULT NULL,
  `price_usd` decimal(40,20) DEFAULT NULL,
  `price_btc` decimal(40,20) DEFAULT NULL,
  `est_day` decimal(40,20) DEFAULT NULL,
  `est_week` decimal(40,20) DEFAULT NULL,
  `est_month` decimal(40,20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coin` (`coin`)
) ENGINE=InnoDB AUTO_INCREMENT=234756 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mining_payout_log`
--

DROP TABLE IF EXISTS `mining_payout_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mining_payout_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `coin` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `ts` datetime DEFAULT NULL,
  `quantity` decimal(40,20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `coin` (`coin`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `monedas`
--

DROP TABLE IF EXISTS `monedas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monedas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(3) DEFAULT NULL,
  `simbolo` varchar(24) DEFAULT NULL,
  `local` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `movimientos`
--

DROP TABLE IF EXISTS `movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(512) DEFAULT NULL,
  `comentarios` varchar(2048) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `fecha_confirmacion` datetime DEFAULT NULL,
  `cuotas` int DEFAULT NULL,
  `confirmado` tinyint(1) DEFAULT NULL,
  `importe_origen` decimal(40,20) DEFAULT NULL,
  `importe_destino` decimal(40,20) DEFAULT NULL,
  `id_tipo` int DEFAULT NULL,
  `id_cuenta_origen` int DEFAULT NULL,
  `id_cuenta_destino` int DEFAULT NULL,
  `id_moneda_origen` int DEFAULT NULL,
  `id_moneda_destino` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_tipo` (`id_tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `id_cuenta_origen` int DEFAULT NULL,
  `id_cuenta_concepto` int DEFAULT NULL,
  `id_moneda` int DEFAULT NULL,
  `id_asiento` int DEFAULT NULL,
  `importe` decimal(40,20) DEFAULT NULL,
  `cotizacion` decimal(40,20) DEFAULT NULL,
  `comentarios` varchar(255) DEFAULT NULL,
  `cuotas` int DEFAULT NULL,
  `cuota` int DEFAULT NULL,
  `id_origen` int DEFAULT NULL,
  `codigo` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cuenta_concepto` (`id_cuenta_concepto`),
  KEY `id_cuenta_origen` (`id_cuenta_origen`)
) ENGINE=InnoDB AUTO_INCREMENT=13859 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pais`
--

DROP TABLE IF EXISTS `pais`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pais` (
  `id_pais` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `iso` varchar(3) DEFAULT NULL,
  `idioma` varchar(16) DEFAULT NULL,
  `habilitado` tinyint(1) DEFAULT NULL,
  `cantidad_items` int DEFAULT NULL,
  PRIMARY KEY (`id_pais`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plazos_fijos`
--

DROP TABLE IF EXISTS `plazos_fijos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plazos_fijos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `id_cuenta_banco` int DEFAULT NULL,
  `id_cuenta_plazo_fijo` int DEFAULT NULL,
  `id_cuenta_intereses` int DEFAULT NULL,
  `id_asiento_constitucion` int DEFAULT NULL,
  `id_asiento_vencimiento` int DEFAULT NULL,
  `importe` decimal(40,20) DEFAULT NULL,
  `intereses` decimal(40,20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cuenta_banco` (`id_cuenta_banco`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `provincia`
--

DROP TABLE IF EXISTS `provincia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `provincia` (
  `id_provincia` int NOT NULL AUTO_INCREMENT,
  `id_pais` int DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `valida` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_provincia`),
  KEY `k_pais` (`id_pais`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `puntos_de_venta`
--

DROP TABLE IF EXISTS `puntos_de_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `puntos_de_venta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_cuit_emisor` int DEFAULT NULL,
  `numero` int DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `factura_electronica` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_funcion`
--

DROP TABLE IF EXISTS `sec_funcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_funcion` (
  `id_sec_funcion` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `clave` varchar(255) DEFAULT NULL,
  `accion` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `codigo_padre` varchar(255) DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `condicion` text,
  `accion_alternativa` varchar(255) DEFAULT NULL,
  `orden` int DEFAULT NULL,
  `parametros` text,
  `compania` varchar(255) DEFAULT NULL,
  `a_modified_by` varchar(255) DEFAULT NULL,
  `a_modified` datetime DEFAULT NULL,
  `a_locked_by` varchar(255) DEFAULT NULL,
  `a_locked` datetime DEFAULT NULL,
  `a_version` varchar(255) DEFAULT NULL,
  `mensaje_confirmacion` text,
  `icono` varchar(255) DEFAULT NULL,
  `popup` int DEFAULT NULL,
  PRIMARY KEY (`id_sec_funcion`),
  UNIQUE KEY `idx_unique_sec_funcion` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_perfil`
--

DROP TABLE IF EXISTS `sec_perfil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_perfil` (
  `id_sec_perfil` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `compania` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `codigo_perfil` varchar(255) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_sec_perfil`),
  UNIQUE KEY `claves_sec_perfil` (`codigo_perfil`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_perfil_programa`
--

DROP TABLE IF EXISTS `sec_perfil_programa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_perfil_programa` (
  `id_sec_perfil_programa` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `compania` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `id_sec_perfil` int DEFAULT NULL,
  `id_sec_programa` int DEFAULT NULL,
  `acceso_directo` int DEFAULT NULL,
  `acceso_directo_orden` int DEFAULT NULL,
  `modulo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_sec_perfil_programa`),
  UNIQUE KEY `idx_unique_sec_perfil_programa` (`id_sec_perfil`,`id_sec_programa`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_perfil_programa_funcion`
--

DROP TABLE IF EXISTS `sec_perfil_programa_funcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_perfil_programa_funcion` (
  `id_sec_perfil_programa_funcion` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `compania` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `id_sec_funcion` int DEFAULT NULL,
  `id_sec_perfil_programa` int DEFAULT NULL,
  PRIMARY KEY (`id_sec_perfil_programa_funcion`),
  UNIQUE KEY `claves_sec_perfil_programa_funcion` (`fecha_baja`,`baja`,`id_sec_perfil_programa`,`id_sec_funcion`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_programa`
--

DROP TABLE IF EXISTS `sec_programa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_programa` (
  `id_sec_programa` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `clave` varchar(255) DEFAULT NULL,
  `url_destino` varchar(255) DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `codigo_padre` varchar(255) DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `compania` varchar(255) DEFAULT NULL,
  `a_modified_by` varchar(255) DEFAULT NULL,
  `a_modified` datetime DEFAULT NULL,
  `a_locked_by` varchar(255) DEFAULT NULL,
  `a_locked` datetime DEFAULT NULL,
  `a_version` varchar(255) DEFAULT NULL,
  `modulo` varchar(255) DEFAULT NULL,
  `ayuda` text,
  `icono_acceso_directo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_sec_programa`),
  UNIQUE KEY `idx_unique_sec_programa` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_programa_funcion`
--

DROP TABLE IF EXISTS `sec_programa_funcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_programa_funcion` (
  `id_sec_funcion` int DEFAULT NULL,
  `id_sec_programa` int DEFAULT NULL,
  `id_sec_programa_funcion` int NOT NULL AUTO_INCREMENT,
  `codigo_padre` varchar(255) DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `orden` int DEFAULT NULL,
  `compania` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_sec_programa_funcion`),
  KEY `id_funcion` (`id_sec_funcion`),
  KEY `id_programa` (`id_sec_programa`),
  KEY `id_funcion_2` (`id_sec_funcion`),
  KEY `id_programa_2` (`id_sec_programa`),
  KEY `id_funcion_3` (`id_sec_funcion`),
  KEY `id_programa_3` (`id_sec_programa`),
  KEY `id_funcion_4` (`id_sec_funcion`),
  KEY `id_programa_4` (`id_sec_programa`),
  KEY `id_funcion_5` (`id_sec_funcion`),
  KEY `id_programa_5` (`id_sec_programa`),
  KEY `id_funcion_6` (`id_sec_funcion`),
  KEY `id_programa_6` (`id_sec_programa`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_usuario`
--

DROP TABLE IF EXISTS `sec_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_usuario` (
  `id_sec_usuario` int NOT NULL AUTO_INCREMENT,
  `fecha_creacion` datetime DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `utlima_url` varchar(255) DEFAULT NULL,
  `url_inicio` varchar(255) DEFAULT NULL,
  `invalidar_seguridad` int DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `usuario` varchar(64) DEFAULT NULL,
  `clave` varchar(256) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `bloqueado` tinyint(1) DEFAULT NULL,
  `tipo` int DEFAULT NULL,
  `email_confirmado` tinyint(1) DEFAULT NULL,
  `email_confirmado01` tinyint(1) DEFAULT NULL,
  `email_nuevo` varchar(255) DEFAULT NULL,
  `perfil` varchar(255) DEFAULT NULL,
  `suscripto` int DEFAULT NULL,
  `fecha_suscripcion` date DEFAULT NULL,
  `genero` varchar(2) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `ciudad` varchar(255) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `codigo_postal` varchar(255) DEFAULT NULL,
  `telefono_area` varchar(255) DEFAULT NULL,
  `telefono_numero` varchar(255) DEFAULT NULL,
  `telefono2_area` varchar(255) DEFAULT NULL,
  `telefono2_numero` varchar(255) DEFAULT NULL,
  `documento_tipo` varchar(255) DEFAULT NULL,
  `documento_numero` varchar(255) DEFAULT NULL,
  `web` varchar(255) DEFAULT NULL,
  `es_cliente` tinyint(1) DEFAULT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  `fb_username` varchar(255) DEFAULT NULL,
  `gp_username` varchar(255) DEFAULT NULL,
  `tw_username` varchar(255) DEFAULT NULL,
  `id_pais` int DEFAULT NULL,
  `id_provincia` int DEFAULT NULL,
  `id_imagen` int DEFAULT NULL,
  `cookie_id` varchar(255) DEFAULT NULL,
  `social_provider` varchar(255) DEFAULT NULL,
  `email_confirmacion` varchar(255) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT NULL,
  `password_codigo_recuperacion` varchar(255) DEFAULT NULL,
  `pais` varchar(255) DEFAULT NULL,
  `id_formdef` int DEFAULT NULL,
  PRIMARY KEY (`id_sec_usuario`),
  UNIQUE KEY `idx_unique_sec_usuario` (`baja`,`usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sec_usuario_perfil`
--

DROP TABLE IF EXISTS `sec_usuario_perfil`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sec_usuario_perfil` (
  `id_sec_usuario_perfil` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT NULL,
  `usuario_creacion` int DEFAULT NULL,
  `fecha_ult_modif` datetime DEFAULT NULL,
  `usuario_ult_modif` int DEFAULT NULL,
  `fecha_baja` datetime DEFAULT NULL,
  `baja` int DEFAULT NULL,
  `modificable` int DEFAULT NULL,
  `compania` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `version` varchar(255) DEFAULT NULL,
  `locked` datetime DEFAULT NULL,
  `usuario_lockeo` int DEFAULT NULL,
  `mtd_ref` int DEFAULT NULL,
  `id_sec_perfil` int DEFAULT NULL,
  `id_sec_usuario` int DEFAULT NULL,
  PRIMARY KEY (`id_sec_usuario_perfil`),
  UNIQUE KEY `claves_sec_usuario_perfil` (`id_sec_usuario`,`id_sec_perfil`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `template`
--

DROP TABLE IF EXISTS `template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template` (
  `id_template` int NOT NULL AUTO_INCREMENT,
  `code` varchar(255) DEFAULT NULL,
  `subject` text,
  `body` text,
  PRIMARY KEY (`id_template`),
  UNIQUE KEY `template_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_comprobante`
--

DROP TABLE IF EXISTS `tipos_comprobante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_comprobante` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_afip` int DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `descripcion_corta` varchar(8) DEFAULT NULL,
  `letra` varchar(1) DEFAULT NULL,
  `signo` int DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_comprobante_rel`
--

DROP TABLE IF EXISTS `tipos_comprobante_rel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_comprobante_rel` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_condicion_iva_emisor` int DEFAULT NULL,
  `id_condicion_iva_destinatario` int DEFAULT NULL,
  `id_tipo_comprobante` int DEFAULT NULL,
  `exportacion` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_doc`
--

DROP TABLE IF EXISTS `tipos_doc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_doc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_afip` int DEFAULT NULL,
  `descripcion` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tipos_movimiento`
--

DROP TABLE IF EXISTS `tipos_movimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tipos_movimiento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(255) DEFAULT NULL,
  `id_rubro_origen` int DEFAULT NULL,
  `id_rubro_destino` int DEFAULT NULL,
  `descripcion_origen` varchar(255) DEFAULT NULL,
  `descripcion_destino` varchar(255) DEFAULT NULL,
  `descripcion_importe_origen` varchar(255) DEFAULT NULL,
  `descripcion_importe_destino` varchar(255) DEFAULT NULL,
  `rubro_destino` varchar(255) DEFAULT NULL,
  `rubro_origen` varchar(255) DEFAULT NULL,
  `cuotas` tinyint(1) DEFAULT NULL,
  `codigo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-09 22:43:01
