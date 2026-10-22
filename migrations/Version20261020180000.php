<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020180000 extends AbstractMigration 
{
	public function getDescription():string{
		return 'Fuellabende, Viererfuellungen und Kompressorwartung ohne Veraenderung alter Fuellprotokolle';
	}
	
 	public function up(Schema $schema):void 
	{
  		$this->addSql(
			"CREATE TABLE compressor_fill_session (
				id INT AUTO_INCREMENT NOT NULL, 
				club_id INT NOT NULL, 
				operator_id INT NOT NULL, 
				compressor VARCHAR(100) NOT NULL, 
				opening_hours_tenths INT NOT NULL, 
				closing_hours_tenths INT DEFAULT NULL,
				system_checked TINYINT(1) NOT NULL, 
				filter_checked TINYINT(1) NOT NULL, 
				air_status_checked TINYINT(1) NOT NULL, 
				notes LONGTEXT DEFAULT NULL, 
				opened_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', 
				closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', 
				INDEX IDX_CFS_CLUB (club_id), 
				INDEX IDX_CFS_USER (operator_id), 
				PRIMARY KEY(id)) 
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB"
		);
		
  		$this->addSql(
			"CREATE TABLE compressor_fill_batch (
				id INT AUTO_INCREMENT NOT NULL, 
				session_id INT NOT NULL, 
				recorded_by_id INT NOT NULL, 
				end_pressure_bar INT NOT NULL, 
				notes LONGTEXT DEFAULT NULL, 
				filled_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', 
				INDEX IDX_CFB_SESSION (session_id), 
				INDEX IDX_CFB_USER (recorded_by_id), 
				PRIMARY KEY(id)) 
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB"
		);
  		
		$this->addSql(
			"CREATE TABLE compressor_fill_cylinder (
				id INT AUTO_INCREMENT NOT NULL, 
				batch_id INT NOT NULL, 
				tank_id INT DEFAULT NULL, 
				volume_liters NUMERIC(5, 1) DEFAULT NULL,
				identification VARCHAR(100) NOT NULL, 
				cylinder_checked TINYINT(1) NOT NULL, 
				INDEX IDX_CFC_BATCH (batch_id), 
				INDEX IDX_CFC_TANK (tank_id), 
				PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB");
  		
		$this->addSql(
			"CREATE TABLE compressor_maintenance (
				id INT AUTO_INCREMENT NOT NULL, 
				club_id INT NOT NULL,
				recorded_by_id INT NOT NULL, 
				compressor VARCHAR(100) NOT NULL, 
				kind VARCHAR(30) NOT NULL, 
				description LONGTEXT NOT NULL, 
				performed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', 
				due_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', 
				INDEX IDX_CM_CLUB (club_id), 
				INDEX IDX_CM_USER (recorded_by_id), 
				PRIMARY KEY(id)) 
				DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB"
		);
		
	foreach (
		['compressor_fill_session'=>[['club_id','club'],
			['operator_id','`user`']
			],
			'compressor_fill_batch'=>[
				['session_id','compressor_fill_session'],
				['recorded_by_id','`user`']
			],
			'compressor_fill_cylinder'=>[
				['batch_id','compressor_fill_batch'],
				['tank_id','tank']
			],
			'compressor_maintenance'=>[
				['club_id','club'],
				['recorded_by_id','`user`']
			]
		] as $table=>$links) {
			foreach($links as $link)
			{
				$this->addSql('ALTER TABLE '.$table.' ADD FOREIGN KEY ('.$link[0].') REFERENCES '.$link[1].' (id)'.($table==='compressor_fill_cylinder' && $link[0]==='tank_id'?' ON DELETE SET NULL':''));
			}
		}
	}
	
	public function down(Schema $schema):void 
	{
		foreach(['compressor_fill_cylinder','compressor_fill_batch','compressor_fill_session','compressor_maintenance'] as $table){
			$this->addSql('DROP TABLE '.$table);
		}
	}
}
