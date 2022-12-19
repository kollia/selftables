<?php


class STDbUpdater
{
    /**
     * current database table
     * @var STDbTable table
     */
	var	$table;
	/**
	 * updating content of columns
	 * for more than one row
	 * @var array columns
	 */
	var $columns= array();
	/**
	 * current row which can be filled
	 * with new content
	 * @var integer
	 */
	var $nAktRow= 0;
	/**
	 * where statement for all rows
	 * @var array
	 */
	//var $wheres= array();
	/**
	 * exist sql statement
	 * @var string array
	 */
	private $statements= array();
	
	function __construct(&$oTable)
	{
	    Tag::paramCheck($oTable, 1, "STDbTable");
		$this->table= &$oTable;
	}
	function update($column, $value)
	{
		Tag::paramCheck($column, 1, "string");
		
		$this->columns[$this->nAktRow][$column]= $value;
	}
	function where($where)
	{
		$this->wheres[$this->nAktRow]= $where;
	}
	function fillNextRow()
	{
		++$this->nAktRow;
	}
	public function getStatement($nr= 0) : string
	{
	    if(!isset($this->statements[$nr]))
	    {
	        $where= null;
	        if(isset($this->wheres[$nr]))
	            $where= $this->wheres[$nr];
	        $this->statement[$nr]= $this->table->db->getUpdateStatement($this->table, $where, $this->columns[$nr]);
	    }
	    return $this->statement[$nr];
	}
	public function execute($onError= onErrorStop)
	{
	  if(!count($this->columns))
		    return 0;
		$db= &$this->table->db;
		$this->nErrorRowNr= null;
		//st_print_r($this->columns,2);
		foreach($this->columns as $nr=>$columns)
		{
		    $statement= $this->getStatement($nr);
		    $db->query($statement, $onError);
			if($db->errno())
			{
				$this->nErrorRowNr= $nr;
				break;
			}
		}
		if($this->nErrorRowNr!==null)
		{
			$newRows= array();
			$oldCount= count($this->columns);
			for($o= $this->nErrorRowNr; $o<$oldCount; $o++)
				$newRows[]= $this->columns[$o];
			$this->columns= $newRows;
		}
		//echo "error:".$db->errno()."<br />";
		return $db->errno();
	}
	function getErrorString()
	{
		$errorString= "";
		if($this->table->db->errno())
  			$errorString= "by row ".$this->nErrorRowNr.": ";
		$errorString.= $this->table->db->getError();
		return $errorString;
	}
}
?>