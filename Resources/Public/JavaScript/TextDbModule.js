/*
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 */

/**
 * Moves the flash messages and the ".return" fragment of a module response
 * into the given element, replacing what it held.
 *
 * The backend layout renders the flash messages a request flushed from the
 * queue in ".typo3-messages-container", outside ".return", so they are moved
 * along or they are lost (#142). The container keeps its role="alert", so a
 * screen reader announces the inserted messages. Nodes are moved out of the
 * inert DOMParser document, never inserted as an HTML string.
 *
 * Returns false and leaves the element unchanged when the response carries
 * neither, for example an error page.
 */
function moveResponseInto(content, element)
{
    const messages = content.querySelector(".typo3-messages-container");
    const hasMessages = messages !== null && messages.querySelector(".alert") !== null;
    const contentReturn = content.querySelector(".return");

    if (!hasMessages && contentReturn === null) {
        return false;
    }

    element.replaceChildren();

    if (hasMessages) {
        element.appendChild(messages);
    }

    if (contentReturn !== null) {
        while (contentReturn.firstChild !== null) {
            element.appendChild(contentReturn.firstChild);
        }
    }

    return true;
}

class TextDbModule
{
    constructor()
    {
        // Click to open translated item
        document
            .querySelectorAll(".translated-link-open")
            .forEach(
                (linkOpen) => linkOpen.addEventListener(
                    "click",
                    (event) => {
                        event.preventDefault();

                        document
                            .querySelectorAll(".translated-link-close")
                            .forEach(linkClose => linkClose.click());

                        const url = event.currentTarget.getAttribute("href");
                        const uid = event.currentTarget.getAttribute("data-uid");

                        const parentTableRow = event.currentTarget.closest("tr");

                        // Show loading animation
                        let loadingAnimation = parentTableRow
                            .querySelector(".loading-animation");

                        if (loadingAnimation !== null) {
                            loadingAnimation.style.display = "";
                        }

                        fetch(url)
                            .then(response => response.text())
                            .then((html) => {
                                // Initialize the DOM parser and parse to response
                                const parser = new DOMParser();
                                const content = parser.parseFromString(html, "text/html");

                                // Build the new row via DOM APIs instead of insertAdjacentHTML
                                // to avoid HTML injection via the server response.
                                const newRow = document.createElement("tr");
                                newRow.id = "translation-" + uid;

                                const newCell = document.createElement("td");
                                newCell.setAttribute("colspan", "5");

                                moveResponseInto(content, newCell);

                                newRow.appendChild(newCell);
                                parentTableRow.parentNode.insertBefore(
                                    newRow,
                                    parentTableRow.nextSibling
                                );

                                // Hide loading animation
                                if (loadingAnimation !== null) {
                                    loadingAnimation.style.display = "none";
                                }

                                let translatedLinkOpen = parentTableRow.querySelector(".translated-link-open");
                                let translatedLinkClose = parentTableRow.querySelector(".translated-link-close");

                                if (translatedLinkOpen !== null) {
                                    translatedLinkOpen.style.display = "none";
                                }

                                if (translatedLinkClose !== null) {
                                    translatedLinkClose.style.display = "";
                                }
                            });
                    })
            );

        // Click to close translated item
        document
            .querySelectorAll(".translated-link-close")
            .forEach(
                (linkClose) => linkClose.addEventListener(
                    "click",
                    (event) => {
                        event.preventDefault();

                        const uid = event.currentTarget.getAttribute("data-uid");

                        event
                            .currentTarget
                            .style
                            .display = "none";

                        // Show the open link
                        let translatedLinkOpen = event
                            .currentTarget
                            .parentNode
                            .querySelector(".translated-link-open");

                        if (translatedLinkOpen !== null) {
                            translatedLinkOpen.style.display = "";
                        }

                        // Remove translated item
                        const translationRow = document
                            .getElementById("translation-" + uid);

                        if (translationRow !== null) {
                            translationRow.remove();
                        }
                    })
            );

        // Submit a translation form
        let textdbTable = document
            .querySelector("table#tx_nrtextdb");

        if (textdbTable !== null) {
            textdbTable
                .addEventListener("submit", async (event) => {
                    if (event.target.classList.contains("translation-form")) {
                        event.preventDefault();

                        const action = event.target.getAttribute("action");
                        const uid = event.target.getAttribute("data-uid");

                        // Show loading animation
                        let loadingAnimation = document
                            .getElementById("entry-" + uid)
                            .querySelector(".loading-animation");

                        if (loadingAnimation !== null) {
                            loadingAnimation.style.display = "";
                        }

                        try {
                            const response = await fetch(
                                action,
                                {
                                    method: "POST",
                                    body: new FormData(event.target),
                                }
                            );

                            const html = await response.text();

                            // Initialize the DOM parser and parse to response
                            const parser = new DOMParser();
                            const content = parser.parseFromString(html, "text/html");

                            let translationTableData = document
                                .getElementById("translation-" + uid)
                                .querySelector("td");

                            if (translationTableData !== null) {
                                moveResponseInto(content, translationTableData);
                            }

                            // Hide loading animation
                            if (loadingAnimation !== null) {
                                loadingAnimation.style.display = "none";
                            }
                        } catch (e) {
                            console.error(e);
                        }
                    }
                });
        }
    }
}
export default new TextDbModule;
