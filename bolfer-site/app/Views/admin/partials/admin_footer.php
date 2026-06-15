<?php
if (!empty($isAuthPage)) : ?>
  </main>
<?php else : ?>
    </main>
  </div>
<?php endif; ?>
<script>
(() => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  if (!csrfToken) {
    return;
  }

  const postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');
  postForms.forEach((form) => {
    if (!(form instanceof HTMLFormElement) || form.querySelector('input[name="csrf_token"]')) {
      return;
    }

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'csrf_token';
    input.value = csrfToken;
    form.appendChild(input);
  });
})();
</script>
</body>
</html>
