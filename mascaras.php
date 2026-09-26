<script>
/* =========================================================
   Máscaras reutilizáveis
   Uso: <input data-mask="cpf"> ou data-mask="cnpj|telefone|celular|cep|cpf_cnpj"
   ========================================================= */
(function () {
  const soDigitos = v => v.replace(/\D+/g, '');

  window.Mask = {
    cpf(v) {
      v = soDigitos(v).slice(0, 11);
      v = v.replace(/^(\d{3})(\d)/, '$1.$2');
      v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
      v = v.replace(/\.(\d{3})(\d)/, '.$1-$2');
      return v;
    },
    cnpj(v) {
      v = soDigitos(v).slice(0, 14);
      v = v.replace(/^(\d{2})(\d)/, '$1.$2');
      v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
      v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
      v = v.replace(/(\d{4})(\d)/, '$1-$2');
      return v;
    },
    cpf_cnpj(v) {
      const d = soDigitos(v);
      return d.length <= 11 ? this.cpf(v) : this.cnpj(v);
    },
    telefone(v) {
      v = soDigitos(v).slice(0, 10);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{4})(\d)/, '$1-$2');
      return v;
    },
    celular(v) {
      v = soDigitos(v).slice(0, 11);
      v = v.replace(/^(\d{2})(\d)/, '($1) $2');
      v = v.replace(/(\d{5})(\d)/, '$1-$2');
      return v;
    },
    cep(v) {
      v = soDigitos(v).slice(0, 8);
      return v.replace(/^(\d{5})(\d)/, '$1-$2');
    }
  };

  function aplicar(el) {
    const tipo = el.dataset.mask;
    const fn   = Mask[tipo];
    if (typeof fn !== 'function') return;
    const exec = () => { el.value = fn(el.value); };
    el.addEventListener('input', exec);
    el.addEventListener('blur',  exec);
    exec(); // aplica no load
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-mask]').forEach(aplicar);
  });
})();
</script>