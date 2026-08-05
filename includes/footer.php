        </main>
    </div>
</div>

<script>
// Global utility functions for UI
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}
</script>
</body>
</html>
