<style>
    html, body {
        min-height: 100% !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
    }

    div[wire\:id] {
        min-height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
    }

    @media (min-width: 900px) {
        .desktop-filter-scroll {
            height: calc(100vh - 420px) !important;
            overflow-y: auto !important;
            padding-right: 4px !important;
        }

        .desktop-workspace-area {
            display: flex !important;
            flex-direction: row !important;
            gap: 24px !important;
            align-items: stretch !important;
            padding-right: 360px !important;
        }

        .desktop-filter-panel {
            display: flex !important;
            flex-direction: column !important;
            gap: 20px !important;
            position: fixed !important;
            top: 190px !important;
            right: 24px !important;
            width: 320px !important;
            max-height: calc(100vh - 266px) !important;
            z-index: 40 !important;
            align-self: flex-start !important;
            background: #ffffff !important;
            pointer-events: auto !important;
            isolation: isolate !important;
        }
    }

    @media (max-width: 899px) {
        .desktop-filter-panel {
            display: none !important;
        }
    }

    .dashboard-fixed-footer {
        display: none;
    }

    .datepicker-modal-container {
        display: flex !important;
        flex-direction: column !important;
    }

    .datepicker-left-panel {
        width: 100% !important;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    @media (min-width: 900px) {
        .dashboard-fixed-footer {
            display: flex !important;
            position: fixed !important;
            left: 64px !important;
            right: 368px !important;
            bottom: 0 !important;
            z-index: 18 !important;
            background: rgba(247, 249, 255, 0.94) !important;
            backdrop-filter: blur(10px) !important;
        }

        .datepicker-modal-container {
            flex-direction: row !important;
            height: 450px !important;
        }

        .datepicker-left-panel {
            width: 200px !important;
            border-bottom: none !important;
            border-right: 1px solid #f1f5f9 !important;
        }
    }
</style>
