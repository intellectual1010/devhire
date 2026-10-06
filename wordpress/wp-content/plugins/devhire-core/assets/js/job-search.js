document.addEventListener(
    "DOMContentLoaded",
    () => {

        const form =
            document.querySelector(
                "#devhire-job-filter"
            );

        const results =
            document.querySelector(
                "#devhire-job-results"
            );

        const count =
            document.querySelector(
                "#devhire-job-count"
            );


        if (!form || !results) {
            return;
        }


        let currentRequest = null;


        async function searchJobs(page = 1) {

            /*
             * Cancel previous request when
             * filters change quickly.
             */
            if (currentRequest) {
                currentRequest.abort();
            }


            currentRequest =
                new AbortController();


            const formData =
                new FormData(form);


            formData.append(
                "action",
                "devhire_job_search"
            );

            formData.append(
                "nonce",
                devhireJobSearch.nonce
            );

            formData.append(
                "page",
                page
            );


            results.classList.add(
                "is-loading"
            );


            try {

                const response = await fetch(
                    devhireJobSearch.ajaxUrl,
                    {
                        method: "POST",

                        body: formData,

                        credentials:
                            "same-origin",

                        signal:
                            currentRequest.signal,
                    }
                );


                if (!response.ok) {
                    throw new Error(
                        "Search request failed."
                    );
                }


                const data =
                    await response.json();


                if (!data.success) {
                    throw new Error(
                        "Unable to search jobs."
                    );
                }


                results.innerHTML =
                    data.data.html;


                if (count) {

                    count.textContent =
                        new Intl.NumberFormat()
                            .format(
                                data.data.total
                            );
                }


                updateUrl(page);


            } catch (error) {

                if (
                    error.name !==
                    "AbortError"
                ) {

                    console.error(error);
                }

            } finally {

                results.classList.remove(
                    "is-loading"
                );

                currentRequest = null;
            }
        }


        function updateUrl(page) {

            const params =
                new URLSearchParams(
                    new FormData(form)
                );


            /*
             * Remove empty values.
             */
            for (
                const [key, value]
                of params.entries()
            ) {

                if (!value) {
                    params.delete(key);
                }
            }


            if (page > 1) {
                params.set("paged", page);
            } else {
                params.delete("paged");
            }


            const query =
                params.toString();


            const url =
                query
                    ? `${window.location.pathname}?${query}`
                    : window.location.pathname;


            window.history.replaceState(
                {},
                "",
                url
            );
        }


        /*
         * Normal form submit.
         */
        form.addEventListener(
            "submit",
            (event) => {

                event.preventDefault();

                searchJobs(1);
            }
        );


        /*
         * Automatically search when
         * dropdown changes.
         */
        form
            .querySelectorAll("select")
            .forEach((select) => {

                select.addEventListener(
                    "change",
                    () => {
                        searchJobs(1);
                    }
                );
            });


        /*
         * AJAX pagination.
         */
        results.addEventListener(
            "click",
            (event) => {

                const button =
                    event.target.closest(
                        ".ajax-page"
                    );


                if (!button) {
                    return;
                }


                const page =
                    Number(
                        button.dataset.page
                    );


                if (!page) {
                    return;
                }


                searchJobs(page);


                window.scrollTo({
                    top:
                        form.getBoundingClientRect()
                            .top +
                        window.scrollY -
                        100,

                    behavior: "smooth",
                });
            }
        );
    }
);