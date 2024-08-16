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
                        parentTableRow
                            .querySelector(".loading-animation")
                            .style
                            .display = "";

                        fetch(url)
                            .then(response => response.text())
                            .then((html) => {
                                // Initialize the DOM parser and parse to response
                                const parser = new DOMParser();
                                const content = parser.parseFromString(html, "text/html");

                                parentTableRow.insertAdjacentHTML(
                                    "afterend",
                                    '<tr id="translation-' + uid+ '"><td colSpan="5">'
                                    + content.querySelector('.return').innerHTML + '</td></tr>'
                                );

                                // Hide loading animation
                                parentTableRow
                                    .querySelector(".loading-animation")
                                    .style
                                    .display = "none";

                                parentTableRow
                                    .querySelector(".translated-link-open")
                                    .style
                                    .display = "none";

                                parentTableRow
                                    .querySelector(".translated-link-close")
                                    .style
                                    .display = "";
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
                        event
                            .currentTarget
                            .parentNode
                            .querySelector(".translated-link-open")
                            .style
                            .display = "";

                        // Remove translated item
                        const translationRow = document
                            .getElementById("translation-" + uid);

                        if (translationRow !== null) {
                            translationRow.remove();
                        }
                    })
            );

        // Submit a translation form
        document
            .querySelector("table#tx_nrtextdb")
            .addEventListener("submit", async (event) => {
                if (event.target.classList.contains("translation-form")) {
                    event.preventDefault();

                    const action = event.target.getAttribute("action");
                    const uid = event.target.getAttribute("data-uid");

                    // Show loading animation
                    document
                        .getElementById("entry-" + uid)
                        .querySelector(".loading-animation")
                        .style
                        .display = "";

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

                        document
                            .getElementById("translation-" + uid)
                            .querySelector("td")
                            .innerHTML = content.querySelector('.return').innerHTML;

                        // Hide loading animation
                        document
                            .getElementById("entry-" + uid)
                            .querySelector(".loading-animation")
                            .style
                            .display = "none";
                    } catch (e) {
                        console.error(e);
                    }
                }
            });
    }
}
export default new TextDbModule;
