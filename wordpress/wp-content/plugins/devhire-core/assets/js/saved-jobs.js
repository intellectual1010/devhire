document.addEventListener("DOMContentLoaded", () => {
    const STORAGE_KEY = "devhire_saved_jobs";

    function getSavedJobs() {
        try {
            const jobs = JSON.parse(
                localStorage.getItem(STORAGE_KEY)
            );

            return Array.isArray(jobs) ? jobs : [];
        } catch {
            return [];
        }
    }

    function setSavedJobs(jobs) {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(jobs)
        );

        window.dispatchEvent(
            new CustomEvent("devhireSavedJobsUpdated")
        );
    }

    function isSaved(jobId) {
        return getSavedJobs().includes(
            Number(jobId)
        );
    }

    function updateButton(button) {
        const jobId = Number(
            button.dataset.jobId
        );

        const saved = isSaved(jobId);

        button.classList.toggle(
            "saved",
            saved
        );

        button.textContent = saved
            ? "✓ Saved"
            : "♡ Save Job";
    }

    async function validateJob(jobId) {
        const formData = new FormData();

        formData.append(
            "action",
            "devhire_validate_job"
        );

        formData.append(
            "nonce",
            devhireSavedJobs.nonce
        );

        formData.append(
            "job_id",
            jobId
        );

        const response = await fetch(
            devhireSavedJobs.ajaxUrl,
            {
                method: "POST",
                body: formData,
                credentials: "same-origin",
            }
        );

        if (!response.ok) {
            throw new Error(
                "Unable to validate job."
            );
        }

        const result = await response.json();

        if (!result.success) {
            throw new Error(
                result.data?.message ||
                "Unable to save job."
            );
        }

        return result.data;
    }

    document
        .querySelectorAll(".devhire-save-job")
        .forEach((button) => {

            updateButton(button);

            button.addEventListener(
                "click",
                async () => {

                    const jobId = Number(
                        button.dataset.jobId
                    );

                    if (!jobId) {
                        return;
                    }

                    /*
                     * Remove existing saved job.
                     */
                    if (isSaved(jobId)) {

                        const jobs = getSavedJobs()
                            .filter(
                                (id) => id !== jobId
                            );

                        setSavedJobs(jobs);

                        updateButton(button);

                        return;
                    }


                    const originalText =
                        button.textContent;

                    button.disabled = true;
                    button.textContent =
                        "Saving...";

                    try {

                        await validateJob(jobId);

                        const jobs =
                            getSavedJobs();

                        if (!jobs.includes(jobId)) {
                            jobs.push(jobId);
                        }

                        setSavedJobs(jobs);

                        updateButton(button);

                    } catch (error) {

                        console.error(error);

                        button.textContent =
                            "Unable to save";

                        setTimeout(() => {
                            updateButton(button);
                        }, 1500);

                    } finally {

                        button.disabled = false;

                        if (
                            button.textContent ===
                            originalText
                        ) {
                            updateButton(button);
                        }
                    }
                }
            );
        });


    /*
     * Saved Jobs page.
     */
    const savedJobsContainer =
        document.querySelector(
            "#devhire-saved-jobs"
        );

    if (savedJobsContainer) {
        loadSavedJobs(savedJobsContainer);
    }


    async function loadSavedJobs(container) {

        const jobIds = getSavedJobs();

        if (!jobIds.length) {
            container.innerHTML = `
                <div class="saved-jobs-empty">
                    <h2>No saved jobs yet</h2>
                    <p>
                        Save jobs you're interested in
                        and they'll appear here.
                    </p>
                </div>
            `;

            return;
        }

        container.innerHTML = "";

        for (const jobId of jobIds) {

            try {

                const job =
                    await validateJob(jobId);

                const card =
                    document.createElement(
                        "article"
                    );

                card.className =
                    "saved-job-card";

                const content =
                    document.createElement("div");

                const title =
                    document.createElement("h3");

                const link =
                    document.createElement("a");

                link.href = job.url;
                link.textContent = job.title;

                title.appendChild(link);
                content.appendChild(title);


                const actions =
                    document.createElement("div");

                actions.className =
                    "saved-job-actions";


                const viewLink =
                    document.createElement("a");

                viewLink.href = job.url;
                viewLink.className =
                    "secondary-button";

                viewLink.textContent =
                    "View Job";


                const removeButton =
                    document.createElement(
                        "button"
                    );

                removeButton.type =
                    "button";

                removeButton.className =
                    "remove-saved-job";

                removeButton.textContent =
                    "Remove";


                removeButton.addEventListener(
                    "click",
                    () => {

                        const jobs =
                            getSavedJobs()
                                .filter(
                                    (id) =>
                                        id !== jobId
                                );

                        setSavedJobs(jobs);

                        card.remove();

                        if (!jobs.length) {
                            loadSavedJobs(
                                container
                            );
                        }
                    }
                );


                actions.appendChild(viewLink);
                actions.appendChild(
                    removeButton
                );

                card.appendChild(content);
                card.appendChild(actions);

                container.appendChild(card);

            } catch (error) {

                /*
                 * Job may have been deleted/unpublished.
                 * Remove stale ID automatically.
                 */
                const jobs = getSavedJobs()
                    .filter(
                        (id) => id !== jobId
                    );

                setSavedJobs(jobs);
            }
        }
    }
});