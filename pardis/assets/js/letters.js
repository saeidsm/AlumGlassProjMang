/* Extracted from pardis/letters.php during Phase 2C.
 * Concatenates 1 inline <script> block(s).
 */

// Initialize Persian Datepicker
        $('.persian-datepicker').persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            calendar: { 
                persian: { 
                    locale: 'fa',
                    leapYearMode: 'astronomical'
                } 
            }
        });
        
        // Sorting function
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentDir = urlParams.get('dir') || 'DESC';
            
            if (currentSort === column) {
                urlParams.set('dir', currentDir === 'ASC' ? 'DESC' : 'ASC');
            } else {
                urlParams.set('sort', column);
                urlParams.set('dir', 'ASC');
            }
            
            window.location.search = urlParams.toString();
        }
        
        // Edit letter function
        function editLetter(id) {
            fetch(`?action=get_letter&id=${id}`)
                .then(r => r.json())
                .then(letter => {
                    if (!letter) {
                        alert('نامه یافت نشد');
                        return;
                    }
                    
                    document.getElementById('edit_letter_id').value = letter.id;
                    document.getElementById('edit_letter_number').value = letter.letter_number || '';
                    document.getElementById('edit_letter_date').value = letter.letter_date_persian || '';
                    document.getElementById('edit_company_sender_id').value = letter.company_sender_id || '';
                    document.getElementById('edit_company_receiver_id').value = letter.company_receiver_id || '';
                    document.getElementById('edit_recipient_name').value = letter.recipient_name || '';
                    document.getElementById('edit_recipient_position').value = letter.recipient_position || '';
                    document.getElementById('edit_subject').value = letter.subject || '';
                    document.getElementById('edit_summary').value = letter.summary || '';
                    document.getElementById('edit_status').value = letter.status || 'draft';
                    document.getElementById('edit_priority').value = letter.priority || 'normal';
                    document.getElementById('edit_category').value = letter.category || '';
                    document.getElementById('edit_content_text').value = letter.content_text || '';
                    document.getElementById('edit_notes').value = letter.notes || '';
                    
                    // Handle tags
                    if (letter.tags) {
                        try {
                            const tags = JSON.parse(letter.tags);
                            document.getElementById('edit_tags').value = Array.isArray(tags) ? tags.join(', ') : '';
                        } catch(e) {
                            document.getElementById('edit_tags').value = '';
                        }
                    } else {
                        document.getElementById('edit_tags').value = '';
                    }
                    
                    // Reinitialize datepicker for edit modal
                    $('#edit_letter_date').persianDatepicker({
                        format: 'YYYY/MM/DD',
                        initialValue: true,
                        autoClose: true,
                        calendar: { 
                            persian: { 
                                locale: 'fa',
                                leapYearMode: 'astronomical'
                            } 
                        }
                    });
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editLetterModal'));
                    editModal.show();
                })
                .catch(err => {
                    console.error('Error loading letter:', err);
                    alert('خطا در بارگذاری اطلاعات نامه');
                });
        }
        
        // Load relationship types
        fetch('?action=get_relationship_types')
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('relationship_type');
                data.forEach(type => {
                    select.innerHTML += `<option value="${type.id}">${type.name_persian}</option>`;
                });
            });
        
        // Deep search functionality
        let searchTimeout;
        const deepSearchInput = document.getElementById('deepSearch');
        const searchResults = document.getElementById('searchResults');
        
        deepSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch(`?action=deep_search&q=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.count > 0) {
                            let html = '<div style="padding: 10px; background: #f0f0f0; font-weight: bold;">' +
                                      `یافت شد: ${data.count} مورد</div>`;
                            
                            data.results.forEach(result => {
                                const sourceIcon = result.source_type === 'attachment' ? 
                                    '<i class="fas fa-paperclip"></i>' : '<i class="fas fa-envelope"></i>';
                                const sourceBadge = result.source_type === 'attachment' ?
                                    '<span class="badge bg-info deep-search-badge">پیوست</span>' :
                                    '<span class="badge bg-primary deep-search-badge">نامه</span>';
                                
                                const matchBadge = result.match_type === 'content' ?
                                    '<span class="badge bg-success deep-search-badge">محتوای فایل</span>' : '';
                                
                                const categoryBadge = result.category ? 
                                    `<span class="badge bg-secondary deep-search-badge">${result.category}</span>` : '';
                                
                                // Format Persian date
                                const persianDate = result.letter_date ? formatPersianDate(result.letter_date) : '';
                                
                                html += `<div class="search-result-item" onclick="viewLetter(${result.id})">
                                    <div>
                                        ${sourceIcon} <strong>${result.letter_number}</strong> ${sourceBadge} ${matchBadge} ${categoryBadge}
                                        ${result.attachment_name ? '<br><small class="text-muted">📎 ' + result.attachment_name + '</small>' : ''}
                                    </div>
                                    <div><small>${result.subject}</small></div>
                                    <div class="text-muted" style="font-size: 0.85em;">
                                        ${result.sender_name} → ${result.receiver_name}
                                        ${persianDate ? ' | تاریخ: ' + persianDate : ''}
                                    </div>
                                </div>`;
                            });
                            
                            searchResults.innerHTML = html;
                            searchResults.style.display = 'block';
                        } else {
                            searchResults.innerHTML = '<div style="padding: 15px; text-align: center; color: #999;">موردی یافت نشد</div>';
                            searchResults.style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error('Search error:', err);
                        searchResults.style.display = 'none';
                    });
            }, 500);
        });
        
        // Format Persian date helper function using Jalali conversion
        function formatPersianDate(gregorianDate) {
            if (!gregorianDate) return '';
            const parts = gregorianDate.split('-');
            if (parts.length !== 3) return '';
            
            const g_y = parseInt(parts[0]);
            const g_m = parseInt(parts[1]);
            const g_d = parseInt(parts[2]);
            
            // Convert Gregorian to Jalali
            const jalali = gregorianToJalali(g_y, g_m, g_d);
            return `${jalali[0]}/${jalali[1].toString().padStart(2, '0')}/${jalali[2].toString().padStart(2, '0')}`;
        }
        
        // Gregorian to Jalali conversion
        function gregorianToJalali(g_y, g_m, g_d) {
            const g_days_in_month = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            const j_days_in_month = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
            
            let gy = g_y - 1600;
            let gm = g_m - 1;
            let gd = g_d - 1;
            
            let g_day_no = 365 * gy + Math.floor((gy + 3) / 4) - Math.floor((gy + 99) / 100) + Math.floor((gy + 399) / 400);
            
            for (let i = 0; i < gm; ++i)
                g_day_no += g_days_in_month[i];
            if (gm > 1 && ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)))
                g_day_no++;
            g_day_no += gd;
            
            let j_day_no = g_day_no - 79;
            
            let j_np = Math.floor(j_day_no / 12053);
            j_day_no = j_day_no % 12053;
            
            let jy = 979 + 33 * j_np + 4 * Math.floor(j_day_no / 1461);
            
            j_day_no %= 1461;
            
            if (j_day_no >= 366) {
                jy += Math.floor((j_day_no - 1) / 365);
                j_day_no = (j_day_no - 1) % 365;
            }
            
            let jm = 0;
            for (let i = 0; i < 11 && j_day_no >= j_days_in_month[i]; ++i) {
                j_day_no -= j_days_in_month[i];
                jm++;
            }
            let jd = j_day_no + 1;
            
            return [jy, jm + 1, jd];
        }
        
        // Clear search
        document.getElementById('clearSearch').addEventListener('click', function() {
            deepSearchInput.value = '';
            searchResults.style.display = 'none';
        });
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!deepSearchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Letter search for relationships
        function setupLetterSearch(inputId, resultsId, hiddenId) {
            const input = document.getElementById(inputId);
            const results = document.getElementById(resultsId);
            const hidden = document.getElementById(hiddenId);
            
            let timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                const query = this.value;
                
                if (query.length < 2) {
                    results.innerHTML = '';
                    return;
                }
                
                timeout = setTimeout(() => {
                    fetch(`?action=search_letters&q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(data => {
                            results.innerHTML = '';
                            data.forEach(letter => {
                                const item = document.createElement('a');
                                item.className = 'list-group-item list-group-item-action';
                                item.href = '#';
                                item.innerHTML = `
                                    <strong>${letter.letter_number}</strong><br>
                                    <small>${letter.subject}</small>
                                `;
                                item.onclick = function(e) {
                                    e.preventDefault();
                                    input.value = letter.letter_number + ' - ' + letter.subject;
                                    hidden.value = letter.id;
                                    results.innerHTML = '';
                                };
                                results.appendChild(item);
                            });
                        });
                }, 300);
            });
        }
        
        setupLetterSearch('parent_search', 'parent_results', 'parent_letter_id');
        setupLetterSearch('child_search', 'child_results', 'child_letter_id');
        
        // View letter details
        function viewLetter(id) {
            window.location.href = `view_letter.php?id=${id}`;
        }
