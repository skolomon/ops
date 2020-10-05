<?php
/**
 * @defgroup controllers_wizard_fileUpload File Upload Wizard
 * The file upload wizard implements the 3-step wizard used to manage
 * uploads of submission files.
 */

/**
 * @file controllers/wizard/fileUpload/FileUploadWizardHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FileUploadWizardHandler
 * @ingroup controllers_wizard_fileUpload
 *
 * @brief A controller that handles basic server-side
 *  operations of the file upload wizard.
 */

// Import the base handler.
import('lib.pkp.controllers.wizard.fileUpload.PKPFileUploadWizardHandler');

class FileUploadWizardHandler extends PKPFileUploadWizardHandler {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		HookRegistry::register('SubmissionFile::assignedFileStageIds', [$this, 'allowAuthorGalleyUploads']);
	}

	/**
	 * Modify the assigned file stage ids to allow authors to upload to files to galleys
	 *
	 * @param string $hookName
	 * @param array $args [
	 * 	@option array The allowed file stage ids
	 *  @option array The current user's stage assignments
	 *  @option int One of SUBMISSION_FILE_READ or SUBMISSION_FILE_ACCESS_MODIFY
	 * ]
	 */
	public function allowAuthorGalleyUploads($hookName, $args) {
		$allowedFileStageIds =& $args[0];
		$stageAssignments = $args[1];

		if (array_key_exists(WORKFLOW_STAGE_ID_PRODUCTION, $stageAssignments)
				&& !empty(in_array(ROLE_ID_AUTHOR, $stageAssignments[WORKFLOW_STAGE_ID_PRODUCTION]))) {
			$allowedFileStageIds[] = SUBMISSION_FILE_PROOF;
		}
	}

	/**
	 * @copydoc PKPFileUploadWizardHandler::_attachEntities
	 */
	protected function _attachEntities($submissionFile) {
		parent::_attachEntities($submissionFile);

		switch ($submissionFile->getFileStage()) {
			case SUBMISSION_FILE_PROOF:
				$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
				assert($submissionFile->getAssocType() == ASSOC_TYPE_REPRESENTATION);
				$galley = $galleyDao->getById($submissionFile->getAssocId());
				if ($galley) {
					$galley->setFileId($submissionFile->getFileId());
					$galleyDao->updateObject($galley);
				}
				break;
		}
	}
}


