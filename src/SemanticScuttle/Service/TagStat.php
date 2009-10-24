<?php
class SemanticScuttle_Service_TagStat extends SemanticScuttle_Service
{

	var $tablename;

    /**
     * Returns the single service instance
     *
     * @param DB $db Database object
     *
     * @return SemanticScuttle_Service
     */
	public static function getInstance($db)
    {
		static $instance;
		if (!isset($instance)) {
            $instance = new self($db);
        }
		return $instance;
	}

	protected function __construct($db)
    {
		$this->db = $db;
		$this->tablename = $GLOBALS['tableprefix'] .'tagsstats';
	}

	function getNbChildren($tag1, $relationType, $uId) {
		$tts =SemanticScuttle_Service_Factory::get('Tag2Tag');
		$query = "SELECT tag1, relationType, uId FROM `". $tts->getTableName() ."`";
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";

		return $this->db->sql_numrows($this->db->sql_query($query));
	}

	function getNbDescendants($tag1, $relationType, $uId) {
		$query = "SELECT nb FROM `". $this->getTableName() ."`";
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";

		$dbresults =& $this->db->sql_query($query);
		$row = $this->db->sql_fetchrow($dbresults);
		if($row['nb'] == null) {
			return 0;
		} else {
			return (int) $row['nb'];
		}
	}

	function getMaxDepth($tag1, $relationType, $uId) {
		$query = "SELECT depth FROM `". $this->getTableName() ."`";
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";

		$dbresults =& $this->db->sql_query($query);
		$row = $this->db->sql_fetchrow($dbresults);
		if($row['depth'] == null) {
			return 0;
		} else {
			return (int) $row['depth'];
		};
	}

	function getNbUpdates($tag1, $relationType, $uId) {
		$query = "SELECT nbupdate FROM `". $this->getTableName() ."`";
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";

		$dbresults =& $this->db->sql_query($query);
		$row = $this->db->sql_fetchrow($dbresults);
		if($row['nbupdate'] == null) {
			return 0;
		} else {
			return (int) $row['nbupdate'];
		}
	}

	function existStat($tag1, $relationType, $uId) {
		$query = "SELECT tag1, relationType, uId FROM `". $this->getTableName() ."`";
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";

		return $this->db->sql_numrows($this->db->sql_query($query))>0;
	}

	function createStat($tag1, $relationType, $uId) {
		$query = "INSERT INTO `". $this->getTableName() ."`";
		$query.= "(tag1, relationType, uId)";
		$query.= " VALUES ('".$tag1."','".$relationType."','".$uId."')";
		$this->db->sql_query($query);
	}

	function updateStat($tag1, $relationType, $uId=null, $stoplist=array()) {
		if(in_array($tag1, $stoplist)) {
			return false;
		}

		$tts =SemanticScuttle_Service_Factory::get('Tag2Tag');
		$linkedTags = $tts->getLinkedTags($tag1, $relationType, $uId);
		$nbDescendants = 0;
		$maxDepth = 0;
		foreach($linkedTags as $linkedTag) {
			$nbDescendants+= 1 + $this->getNbDescendants($linkedTag, $relationType, $uId);
			$maxDepth = max($maxDepth, 1 + $this->getMaxDepth($linkedTag, $relationType, $uId));
		}
		$this->setNbDescendants($tag1, $relationType, $uId, $nbDescendants);
		$this->setMaxDepth($tag1, $relationType, $uId, $maxDepth);
		$this->increaseNbUpdate($tag1, $relationType, $uId);

		// propagation to the precedent tags
		$linkedTags = $tts->getLinkedTags($tag1, $relationType, $uId, true);
		$stoplist[] = $tag1;
		foreach($linkedTags as $linkedTag) {
			$this->updateStat($linkedTag, $relationType, $uId, $stoplist);
		}
	}

	function updateAllStat() {
		$tts =SemanticScuttle_Service_Factory::get('Tag2Tag');

		$query = "SELECT tag1, uId FROM `". $tts->getTableName() ."`";
		$query.= " WHERE relationType = '>'";

		//die($query);

		if (! ($dbresult =& $this->db->sql_query($query)) ){
			message_die(GENERAL_ERROR, 'Could not update stats', '', __LINE__, __FILE__, $query, $this->db);
			return false;
		}

		$rowset = $this->db->sql_fetchrowset($dbresult);
		foreach($rowset as $row) {
			$this->updateStat($row['tag1'], '>', $row['uId']);
		}
	}

	function setNbDescendants($tag1, $relationType, $uId, $nb) {
		if(!$this->existStat($tag1, $relationType, $uId)) {
			$this->createStat($tag1, $relationType, $uId);
		}
		$query = "UPDATE `". $this->getTableName() ."`";
		$query.= " SET nb = ". $nb;
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";
		$this->db->sql_query($query);
	}

	function setMaxDepth($tag1, $relationType, $uId, $depth) {
		if(!$this->existStat($tag1, $relationType, $uId)) {
			$this->createStat($tag1, $relationType, $uId);
		}
		$query = "UPDATE `". $this->getTableName() ."`";
		$query.= " SET depth = ". $depth;
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";
		$this->db->sql_query($query);
	}

	function increaseNbUpdate($tag1, $relationType, $uId) {
		if(!$this->existStat($tag1, $relationType, $uId)) {
			$this->createStat($tag1, $relationType, $uId);
		}
		$query = "UPDATE `". $this->getTableName() ."`";
		$query.= " SET nbupdate = nbupdate + 1";
		$query.= " WHERE tag1 = '" .$tag1 ."'";
		$query.= " AND relationType = '". $relationType ."'";
		$query.= " AND uId = '".$uId."'";

		//die($query);

		$this->db->sql_query($query);
	}

	function deleteTagStatForUser($uId) {
		$query = 'DELETE FROM '. $this->getTableName() .' WHERE uId = '.		intval($uId);

		if (!($dbresult = & $this->db->sql_query($query))) {
			message_die(GENERAL_ERROR, 'Could not delete tag stats', '', __LINE__,
			__FILE__, $query, $this->db);
			return false;
		}

		return true;
	}

	function deleteAll() {
		$query = 'TRUNCATE TABLE `'. $this->getTableName() .'`';
		$this->db->sql_query($query);
	}

	// Properties
	function getTableName()       { return $this->tablename; }
	function setTableName($value) { $this->tablename = $value; }
}
?>