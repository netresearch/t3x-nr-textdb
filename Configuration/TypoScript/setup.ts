
# Module configuration
module.tx_nrtextdb_web_nrtextdbtextdb {
    persistence {
        storagePid = {$module.tx_nrtextdb_textdb.persistence.storagePid}
    }
    view {
        templateRootPaths.0 = EXT:{extension.extensionKey}/Resources/Private/Backend/Templates/
        templateRootPaths.1 = {$module.tx_nrtextdb_textdb.view.templateRootPath}
        partialRootPaths.0 = EXT:nr_textdb/Resources/Private/Backend/Partials/
        partialRootPaths.1 = {$module.tx_nrtextdb_textdb.view.partialRootPath}
        layoutRootPaths.0 = EXT:nr_textdb/Resources/Private/Backend/Layouts/
        layoutRootPaths.1 = {$module.tx_nrtextdb_textdb.view.layoutRootPath}
    }
}
