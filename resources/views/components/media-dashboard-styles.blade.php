<style>
    html, body {
        overflow-x: hidden;
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
    /* ===== MENTIONS FEED SCROLL ===== */
    #mentions-feed-scroll {
        overflow-y: auto !important;
        overflow-x: hidden !important;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    @media (min-width: 900px) {
        .mentions-section-wrapper {
            height: calc(100vh - 170px) !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            gap: 16px !important;
        }
        .mentions-section-wrapper > div:first-child {
            flex-shrink: 0 !important;
        }
        #mentions-feed-scroll {
            flex: 1 1 0px !important;
            min-height: 0 !important;
        }
    }

    @media (max-width: 899px) {
        .mentions-section-wrapper {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
        }
        #mentions-feed-scroll {
            max-height: 75vh !important;
        }
    }
</style>
