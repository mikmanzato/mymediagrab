<?php
/*==============================================================================

	MyMediaGrab

	mmg\Subsource class implementation.

	$Id$

==============================================================================*/

namespace mmg;

use mm\Log;


//------------------------------------------------------------------------------
//! Subdirectory in source
//------------------------------------------------------------------------------
class Subsource
{
	//! Origin path on the source
	public $path = NULL;

	//! Destination subdirectory
	protected $subdir = NULL;

	//! [Source]
	protected $source = NULL;

	//! [SourceStatus]
	protected $sStatus = NULL;

	//! [SubsourceStatus]
	protected $ssStatus = NULL;

	//! Statistics on the last grab operation
	public $numScanned = 0;
	public $numAlreadyProcessed = 0;
	public $numDeleted = 0;
	public $numProcessed = 0;
	public $numStored = 0;
	public $numParked = 0;
	public $numErrors = 0;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Source $source, $path = '.', $subdir = '.')
	{
		$this->source = $source;
		$this->path = $path;
		$this->subdir = $subdir;
	}

	//--------------------------------------------------------------------------
	//! Get list of files in the subsource (sorted by ascending date-time)
	/*! \returns [array(SourceFile)] Found files */
	//--------------------------------------------------------------------------
	protected function listSorted(SubsourceStatus $ssStatus)
	{
		Log::submit(LOG_DEBUG, "Building file list...");

		// Scan all files in subsource path
		// Skip files which have already been processed
		$sourceFiles = array();
		$sdi = new SourceDirIterator($this->source, $this->path);
		while ($sf = $sdi->next()) {
			$this->numScanned++;
			$sourceFiles[] = $sf;
		}

		// Sort by modification time
		// TODO: This may be useless now that "file modification time" demonstrated to be totally unreliable.
		usort($sourceFiles, function($sf1, $sf2) {
				$dt1 = $sf1->getLastModificationDt();
				$dt2 = $sf2->getLastModificationDt();
				return $dt1->getTimestamp() - $dt2->getTimestamp();
			});

		// DEBUG
		//~ foreach ($sourceFiles as $sf) {
			//~ printf("* %s: %s\n", $sf->getLastModificationDt()->format("Y-m-d H:i:s"), $sf->getFName());
		//~ }
		//~ die(__method__."()\n");
		// /DEBUG

		Log::submit(LOG_DEBUG, "Done.");
		return $sourceFiles;
	}

	//--------------------------------------------------------------------------
	//! Grab files from this subsource and deliver them to the configured destination
	//--------------------------------------------------------------------------
	public function grabAndDeliver()
	{
		Log::submit(LOG_DEBUG, sprintf("'%s' --> '%s'", $this->path, $this->subdir));

		$ssStatus = $this->source->getSubsourceStatus($this);

		$sourceFiles = $this->listSorted($ssStatus);
		$destination = $this->source->getDestination();
		foreach ($sourceFiles as $sf) {
			if ($ssStatus->alreadyProcessed($sf)) {
				// File is already processed
				$this->numAlreadyProcessed++;
				if ($this->source->getDelete()) {
					$age = $this->source->getDeleteAge();
					if ($age > 0) {
						$pf = $ssStatus->getProcessed($sf);
						$ts = clone $pf->timestamp;
						$ts->add(new \DateInterval(sprintf("P%dD", $age)));
						$now = new \DateTime("now");
						if ($ts < $now) {
							// Delete file from source
							Log::submit(LOG_INFO, sprintf("Deleting processed file '%s':", $sf->getFName()));
							$ssStatus->clearProcessed($sf);
							$sf->delete();
							$this->numDeleted++;
						}
					}
				}
				continue;
			}

			$res = $destination->store($sf, $this->subdir, $this->source->getOwner());
			$this->numProcessed++;

			switch ($res) {
				case 'saved':			// New file succesfully saved
					$this->numStored++;
					$ssStatus->addProcessed($sf);
					break;

				case 'already-exists':	// File already exists on destination
					$ssStatus->addProcessed($sf);
					break;

				case 'parked':			// File parked
					$this->numParked++;
					$ssStatus->addProcessed($sf);
					break;

				case 'error':			// An error occurred
					$this->numErrors++;
					$delete = FALSE;
					break;

				default:
					Log::submit(LOG_ERR, "Unexpected result: $res");
					$delete = FALSE;
					break;
			}
		}
	}
};
