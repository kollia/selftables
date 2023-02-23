<?php

//global $_stdbinserter;
require_once($_stdbinserter);
require_once($_stsitecreator);
require_once($_stuserclustergroupmanagement);

function groupListCallback(&$callbackObject, $columnName, $rownum)
{
}

function descriptionCallback(&$callbackObject, $columnName, $rownum)
{//print_r($callbackObject->sqlResult[$rownum]);
    //$callbackObject->echoResult();
    //echo "file:".__file__." line:".__line__."<br />";
    if($callbackObject->getValue() == 1)
    {
        $aResult=	array(	"Name"=>"",
            "Description"=>"Zugriff auf alle Projekte und Untergruppen "	);
        $aResult= array($aResult);// es wird eine Zeile vorget�uscht
    }else
    {
        $clusterTable= $callbackObject->getTable("Cluster");
        $cluster= new STDbSelector($clusterTable);
        $cluster->select("Project", "Name");
        $cluster->select("Cluster", "Description");
        $cluster->where("ClusterGroup", "GroupID=".$callbackObject->sqlResult[$rownum]['access to CLUSTERs']);
        $cluster->execute();
        $aResult= $cluster->getResult();
    }
    $source=   "<table>";
    foreach($aResult as $row)
    {
        $source.=  "	<tr>";
        $source.=  "		<td>";
        $source.=  "			<b>";
        $source.=  "				[".$row["Name"]."]";
        $source.=  "			</b>";
        $source.=  "		</td>";
        $source.=  "		<td>";
        $source.=  "				".$row["Description"];
        $source.=  "		</td>";
        $source.=  "	</tr>";
    }
    $source.=  "</table>";
    $callbackObject->setValue($source);
    
    if( $callbackObject->sqlResult[$rownum]["Group"] == "ONLINE" ||
        $callbackObject->sqlResult[$rownum]["Group"] == "LOGGED_IN" )
    {
        $callbackObject->noUnlinkData("delete");
    }
}

class STUserManagement extends STObjectContainer
{
    var $userClusterGroup= null;
    
	function __construct($name, &$container, $bInstall= false)
	{
		STCheck::param($name, 0, "string");
		STCheck::param($container, 1, "STObjectContainer");	
		
		STObjectContainer::__construct($name, $container);
		$this->userClusterGroup= new STUserClusterGroupManagement("UserClusterGroupManagement", $this->getDatabase());
	}
	function create()
	{
	    $this->setDisplayName("Project Management");
	    $this->accessBy("STUM-UserAccess");
		//$this->needContainer("projects");
	    
	    $domain= $this->getTable("AccessDomain");
	    $domain->identifColumn("Name", "Domain");
	    //$domain->select("ID", "Domain");
	    
	    $user= &$this->needTable("User");
	    $user->setDisplayName("User");
	    // *WARNING* column domain was GroupType
	    $user->select("domain", "Domain");
	    $user->preSelect("domain", "custom");
	    $user->disabled("domain");
	    $user->select("user", "User");
	    $user->select("FullName", "full qualified name");
	    $user->select("email", "Email");
	    //$user->select("Description");
	    $user->orderBy("domain");
	    $user->orderBy("user");
	    $user->setMaxRowSelect(50);
	       
	    $groups= &$this->needTable("Group");
	    $groups->setDisplayName("Groups");
	    
		$project= &$this->needTable("Project");
		$project->setDisplayName("existing Projects");
		$this->setFirstTable("Project");
	}
	function init()
	{
	    $session= &STUserSession::instance();
	    
	    $action= $this->getAction();
		$user= &$this->needTable("User");
		
		$groups= &$this->needTable("Group");
		$groups->select("domain", "Domain");
		$groups->preSelect("domain", $session->getCustomDomain()['Name']);
		$groups->disabled("domain");
		$groups->select("Name", "Group");
		
		$project= &$this->needTable("Project");
		$project->select("Name", "Project");
		$project->select("Description");
		$project->select("Path", "Position");
		$project->orderBy("Name");
		
		if($action==STLIST)
		{
		    STCheck::echoDebug("container", "new linked object defined to ".get_class($this)."(<b>$this->name</b>)");
		    $user->select("NrLogin", "logged in");
		    $user->select("LastLogin", "last login");
		    
		    $groups->select("domain", "Domain");
		    $groups->preSelect("domain", $session->getCustomDomain()['name']);
		    $groups->disabled("domain");
		    $groups->select("Name", "Group");
		    $groups->select("ID", "Description", "descriptionCallback");
		    $groups->orderBy("domain");
		    $groups->orderBy("Name");
		    $groups->setMaxRowSelect(50);
		    
		    $project->namedLink("Project", $this->userClusterGroup);
		}else
		{
			$user->select("Pwd");
			$user->password("Pwd", true);
		    $user->passwordNames("new Passwort", "Password repetition");
		}
	}
	function installContainer()
	{
		global $HTTP_SERVER_VARS;
		
		$instance= &STSession::instance();
		
		echo __FILE__.__LINE__."<br>";
		st_print_r($instance, 0);
/*		$partition= $this->getTable("Partition");
		$partition->clearSelects();
		$partition->clearGetColumns();
		$partition->count();
		$selector= new STDbSelector($partition);
		$selector->execute();
		$res= $selector->getSingleResult();*/

		// create custom domain database entry
		$domain= $instance->getCustomDomain();
		
		echo __FILE__.__LINE__."<br>";
    	$projectName= $instance->userManagementProjectName;
    	$project= $this->getTable("Project");
    	$project->clearSelects();
    	$project->clearIdentifColumns();
    	$project->clearGetColumns();
    	$project->select("ID");
		$project->where("Name='".$projectName."'");
    	$selector= new STDbSelector($project);
    	$selector->execute();
    	$userManagementID= $selector->getSingleResult();
    	if($userManagementID)
    		$instance->projectID= $userManagementID;
		else
			$instance->projectID= 1;
	
//		if($res<1)// result STPartition
//		{
			if(!isset($userManagementID))
			{
			    $desc= STDbTableDescriptions::instance($this->getDatabase()->getDatabaseName());
				// fill project-cluster per hand
				// because no project is inserted
				// and the system do not found what we want
				$instance->projectCluster= array(	$desc->getColumnName("Project", "has_access")=>"STUM-Access_".$projectName,
													$desc->getColumnName("Project", "can_insert")=>"STUM-Insert_".$projectName,
													$desc->getColumnName("Project", "can_update")=>"STUM-Update_".$projectName,
													$desc->getColumnName("Project", "can_delete")=>"STUM-Delete_".$projectName	);
    			$project->identifColumn("Name");
        		$project->accessBy("STUM-Access", STLIST);
        		$project->accessBy("STUM-Insert", STINSERT);
        		$project->accessBy("STUM-Update", STUPDATE);
        		$project->accessBy("STUM-Delete", STDELETE);
 /*   			$project->accessCluster("has_access", "Name", "Permission to see the project @");
    			$project->insertCluster("can_insert", "Name", "Permission to create a new project");
    			$project->updateCluster("can_update", "Name", "Changing-Permission at project @");
    			$project->deleteCluster("can_delete", "Name", "Deleting-Permission at project @");*/
    			$inserter= new STDbInserter($project);
    			$inserter->fillColumn("Name", $projectName);
    			$inserter->fillColumn("Path", $HTTP_SERVER_VARS["SCRIPT_NAME"]);
    			$inserter->fillColumn("description", "Listing and changing of all access permissions at project UserManagement");
    			$inserter->fillColumn("DateCreation", "sysdate()");
    			$inserter->execute();

				$userManagementID= $inserter->getLastInsertID();
				if($userManagementID!==1)
				{
					$instance->projectID= $userManagementID;

					$partition= $this->getTable("Partition");
					$updater= new STDbUpdater($partition);
					$updater->update("ProjectID", $userManagementID);
					$updater->execute();

					$cluster= $this->getTable("Cluster");
					$updater= new STDbUpdater($cluster);
					$updater->update("ProjectID", $userManagementID);
					$where= new STDbWhere("ID like 'STUM-Access%'");
					$where->orWhere("ID like 'STUM-Insert%'");
					$where->orWhere("ID like 'STUM-Update%'");
					$where->orWhere("ID like 'STUM-Delete%'");
					$updater->where($where);
					$updater->execute();
				}
			}
			//		}
		$this->createCluster($instance->usermanagementAccessCluster, "Permission to see all projects inside UserManagement", /*addGroup*/true);
		$this->createCluster($instance->usermanagementChangeCluster, "Permission to create projects inside  UserManagement", /*addGroup*/true);			
		
		$this->createGroup($instance->usermanagementAdminGroup); 
		$this->createGroup($instance->onlineGroup);
		$this->createGroup($instance->loggedinGroup);
		
		echo __FILE__.__LINE__."<br>";
		$this->joinClusterGroup($instance->usermanagementAccessCluster, $instance->usermanagementAdminGroup);
		$this->joinClusterGroup($instance->usermanagementChangeCluster, $instance->usermanagementAdminGroup);
		
		
		// select all needed tabels for an join
		// from table-cluster to table-user
		$this->getTable("Cluster");
		$this->getTable("ClusterGroup");

		$user= $this->getTable("User");
		//$user->clearSelects();
		//$user->clearGetColumns();
		//$user->count();
		$selector= new STDbSelector($user);
		$selector->count("User");
		$selector->joinOver("Group");
		$where= new STDbWhere("ID='".$instance->allAdminCluster."'");
		//$where->andWhere("domain=$defaultDomainKey");
		$where->forTable("Cluster");
		echo __FILE__.__LINE__."<br>";
		$selector->where($where);
		$statement= $selector->getStatement();
		echo __FILE__.__LINE__."<br>";
		echo "statement:$statement<br>";
		$selector->execute();
		if(!$selector->getSingleResult())
		{
			$this->createCluster($instance->allAdminCluster, "access to all exist CLUSTERs in every project");

			$db= &$instance->getUserDb();
			$creator= new STSiteCreator($db);
			$creator->setMainContainer("um_install");
			$container= &$creator->getContainer("um_install");
			//STCheck::debug(false);
			$result= $creator->execute();
			if($result=="NOERROR")
			{
				$desc= &STDbTableDescriptions::instance($this->getDatabase()->getDatabaseName());
				$userName= $desc->getColumnName("User", "UserName");
				$pwd= $desc->getColumnName("User", "Pwd");
				$sqlResult= $container->getResult();
				$password= $sqlResult[$pwd];
				preg_match("/^password\('(.+)'\)$/", $password, $preg);
				$password= $preg[1];
				$userId= $this->db->getLastInsertID();
				$this->joinUserGroup($userId, $instance->allAdminCluster);
				$instance->registerSession();
				$instance->acceptUser($sqlResult[$userName], $password);
				$instance->setProperties( $userManagementID );
			}
			$creator->display();
			exit;
		}


		$created= $this->createCluster("STUM-UserAccess", "Berechtigung zum editieren des eigenen User-Accounts");
		if($created==="NOERROR")// if Cluster is created, make an join to the LOGGED_IN group.
			$this->joinClusterGroup("STUM-UserAccess", "LOGGED_IN");// otherwise maybe the admin has changed this
		$this->createCluster("STUM-UserListAccess", "Berechtigung zum ansehen aller Benutzer");
		$this->createCluster("STUM-UserListAdmin", "Berechtigung zum �ndern, l�schen und erstellen der Benutzer");
	}
}

?>