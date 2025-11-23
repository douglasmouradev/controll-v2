// Sistema Help Desk - Controll IT Engineering Technology (Versão com API)

class HelpDeskSystem {
    constructor() {
        this.currentUser = null;
        this.apiBaseUrl = 'api/';
        this.authToken = localStorage.getItem('authToken');
        
        // Flags para controlar listeners
        this.logoutListenerAdded = false;
        this.supportLogoutListenerAdded = false;
        this.adminLogoutListenerAdded = false;
        this.cepButtonListenerAdded = false;
        this.zipcodeListenerAdded = false;
        this.formListenerAdded = false;
        
        this.init();
    }

    init() {
        this.setupEventListeners();
        
        // Verificar se já está logado
        if (this.authToken) {
            this.verifyToken();
        } else {
            this.showPage('loginPage');
        }
    }

    // Métodos de comunicação com API
    async apiCall(endpoint, method = 'GET', data = null, requiresAuth = true) {
        const url = this.apiBaseUrl + endpoint;
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (requiresAuth && this.authToken) {
            options.headers['Authorization'] = 'Bearer ' + this.authToken;
        }
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'Erro na requisição');
            }
            
            return result;
        } catch (error) {
            console.error('Erro na API:', error);
            throw error;
        }
    }

    async verifyToken() {
        try {
            const result = await this.apiCall('auth.php?action=profile', 'GET', null, true);
            this.currentUser = result.data;
            this.login(this.currentUser);
        } catch (error) {
            console.log('Token inválido ou expirado, fazendo logout silencioso');
            // Fazer logout silencioso sem mostrar alertas
            this.currentUser = null;
            this.authToken = null;
            localStorage.removeItem('authToken');
            this.showPage('loginPage');
            this.updateNavigation();
        }
    }

    // Event Listeners
    setupEventListeners() {
        // Usar delegados de eventos para evitar problemas com elementos que ainda não existem
        
        // Login form
        document.getElementById('loginForm')?.addEventListener('submit', (e) => this.handleLogin(e));
        
        // Forgot password
        document.getElementById('forgotPasswordLink')?.addEventListener('click', (e) => {
            e.preventDefault();
            this.showForgotPasswordModal();
        });
        
        // Forgot password form
        document.getElementById('forgotPasswordForm')?.addEventListener('submit', (e) => this.handleForgotPassword(e));
        
        // Logout buttons usando delegado de eventos
        document.addEventListener('click', (e) => {
            const clickedElement = e.target.closest('#logoutBtn, #supportLogoutBtn, #adminLogoutBtn');
            if (clickedElement) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Logout clicado:', clickedElement.id);
                this.logout();
            }
        });
        
        // Admin tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.switchTab(e.target.dataset.tab));
        });
        
        // Filters com verificação de existência
        document.getElementById('statusFilter')?.addEventListener('change', () => this.filterSupportTickets());
        document.getElementById('priorityFilter')?.addEventListener('change', () => this.filterSupportTickets());
        document.getElementById('adminStatusFilter')?.addEventListener('change', () => this.filterAdminTickets());
        document.getElementById('adminPriorityFilter')?.addEventListener('change', () => this.filterAdminTickets());
        document.getElementById('adminUserFilter')?.addEventListener('change', () => this.filterAdminTickets());
        
        // User management - usar delegado de eventos
        document.addEventListener('click', (e) => {
            if (e.target.id === 'addUserBtn') {
                e.preventDefault();
                this.showUserModal();
            }
        });
        
        document.getElementById('userForm')?.addEventListener('submit', (e) => this.handleUserForm(e));
        
        // Modal close buttons usando delegado
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('close')) {
                this.closeModals();
            }
            if (e.target.classList.contains('modal')) {
                this.closeModals();
            }
        });
        
        // Mobile navigation
        document.getElementById('navToggle')?.addEventListener('click', () => this.toggleMobileNav());
    }

    // Authentication
    async handleLogin(e) {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        
        try {
            console.log('Tentando login com:', username);
            
            const result = await this.apiCall('auth.php?action=login', 'POST', {
                username: username,
                password: password
            }, false);
            
            console.log('Resultado do login:', result);
            
            if (result.success && result.data) {
                this.authToken = result.data.token;
                this.currentUser = result.data.user;
                
                localStorage.setItem('authToken', this.authToken);
                
                console.log('Login bem-sucedido! Redirecionando...');
                this.login(this.currentUser);
                
                // Limpar formulário
                document.getElementById('loginForm').reset();
            } else {
                console.error('Resposta inválida da API:', result);
                alert('Usuário ou senha incorretos!');
            }
            
        } catch (error) {
            console.error('Erro no login:', error);
            alert('Usuário ou senha incorretos!');
        }
    }

    showForgotPasswordModal() {
        document.getElementById('forgotPasswordModal').style.display = 'block';
    }

    handleForgotPassword(e) {
        e.preventDefault();
        const email = document.getElementById('forgotEmail').value;
        const username = document.getElementById('forgotUsername').value;
        
        // Verificar se o usuário existe
        const user = this.users.find(u => u.email === email && u.username === username);
        
        if (user) {
            // Simular envio de email (em produção, integrar com serviço de email)
            alert('Link de recuperação enviado para o email cadastrado!');
            this.closeModals();
        } else {
            alert('Email ou usuário não encontrado!');
        }
    }

    login(user) {
        this.currentUser = user;
        
        console.log('Usuário logado:', user); // Debug
        
        switch(user.type) {
            case 'user':
                this.showUserDashboard();
                break;
            case 'support':
                this.showSupportDashboard();
                break;
            case 'admin':
                this.showAdminDashboard();
                break;
            default:
                console.error('Tipo de usuário desconhecido:', user.type);
                alert('Tipo de usuário não reconhecido: ' + user.type);
        }
        
        this.updateNavigation();
    }

    async logout() {
        try {
            // Mostrar feedback visual
            console.log('Iniciando logout...');
            
            // Tentar fazer logout na API se tiver token
            if (this.authToken) {
                try {
                    await this.apiCall('auth.php?action=logout', 'POST');
                } catch (error) {
                    console.log('Erro ao fazer logout na API (continuando mesmo assim):', error);
                }
            }
        } catch (error) {
            console.error('Erro no logout:', error);
        } finally {
            // Limpar dados locais
            this.currentUser = null;
            this.authToken = null;
            localStorage.removeItem('authToken');
            localStorage.removeItem('currentUser');
            
            // Redirecionar para login
            this.showPage('loginPage');
            this.updateNavigation();
            
            // Limpar formulário de login
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.reset();
            }
            
            console.log('Logout concluído');
        }
    }

    // Dashboard Management
    showUserDashboard() {
        this.showPage('userDashboard');
        document.getElementById('userName').textContent = this.currentUser.name;
        this.updateUserStats();
        this.loadUserTickets();
        
        // Resetar flags e adicionar event listeners para elementos do dashboard
        this.logoutListenerAdded = false;
        this.cepButtonListenerAdded = false;
        this.zipcodeListenerAdded = false;
        this.formListenerAdded = false;
        
        // Usar setTimeout para garantir que o DOM está pronto
        setTimeout(() => {
            this.setupDashboardListeners();
        }, 100);
    }

    setupDashboardListeners() {
        console.log('Configurando listeners do dashboard...');
        
        // Logout buttons - adicionar listener direto
        const logoutBtn = document.getElementById('logoutBtn');
        const supportLogoutBtn = document.getElementById('supportLogoutBtn');
        const adminLogoutBtn = document.getElementById('adminLogoutBtn');
        
        console.log('Botões encontrados:', {
            logoutBtn: !!logoutBtn,
            supportLogoutBtn: !!supportLogoutBtn,
            adminLogoutBtn: !!adminLogoutBtn
        });
        
        if (logoutBtn && !this.logoutListenerAdded) {
            console.log('Adicionando listener ao botão logout do usuário');
            logoutBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Botão logout clicado (usuário)');
                this.logout();
                return false;
            };
            this.logoutListenerAdded = true;
        }
        
        if (supportLogoutBtn && !this.supportLogoutListenerAdded) {
            console.log('Adicionando listener ao botão logout do suporte');
            supportLogoutBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Botão logout clicado (suporte)');
                this.logout();
                return false;
            };
            this.supportLogoutListenerAdded = true;
        }
        
        if (adminLogoutBtn && !this.adminLogoutListenerAdded) {
            console.log('Adicionando listener ao botão logout do admin');
            adminLogoutBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('Botão logout clicado (admin)');
                this.logout();
                return false;
            };
            this.adminLogoutListenerAdded = true;
        }
        
        // CEP search button
        const searchCepBtn = document.getElementById('searchCepBtn');
        console.log('Botão buscar CEP encontrado:', !!searchCepBtn);
        
        if (searchCepBtn && !this.cepButtonListenerAdded) {
            console.log('Adicionando listener ao botão buscar CEP');
            searchCepBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('=== BOTÃO BUSCAR CEP CLICADO ===');
                this.searchCep();
                return false;
            });
            this.cepButtonListenerAdded = true;
            console.log('Listener do botão buscar CEP adicionado com sucesso');
        } else if (!searchCepBtn) {
            console.error('ERRO: Botão buscar CEP não encontrado no DOM');
        } else {
            console.log('Listener do botão buscar CEP já foi adicionado anteriormente');
        }
        
        // Formatação de CEP ao digitar e busca automática
        const zipcodeInput = document.getElementById('zipcode');
        console.log('Campo CEP encontrado:', !!zipcodeInput);
        
        if (zipcodeInput && !this.zipcodeListenerAdded) {
            console.log('Adicionando listener ao campo CEP');
            zipcodeInput.oninput = (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 8) {
                    // Formatar CEP apenas quando tiver 5 ou mais dígitos
                    if (value.length >= 5) {
                        e.target.value = value.replace(/(\d{5})(\d{0,3})/, '$1-$2');
                    } else {
                        e.target.value = value;
                    }
                    
                    // Buscar automaticamente quando tiver 8 dígitos
                    if (value.length === 8) {
                        console.log('CEP completo detectado, buscando automaticamente...');
                        setTimeout(() => this.searchCep(), 500);
                    }
                }
            };
            this.zipcodeListenerAdded = true;
        }
        
        // Listener para formulário de novo ticket usando delegado
        const newTicketForm = document.getElementById('newTicketForm');
        if (newTicketForm && !this.formListenerAdded) {
            newTicketForm.onsubmit = (e) => {
                e.preventDefault();
                this.handleNewTicket(e);
            };
            this.formListenerAdded = true;
        }
        
        console.log('Listeners do dashboard configurados');
    }

    showSupportDashboard() {
        this.showPage('supportDashboard');
        document.getElementById('supportUserName').textContent = this.currentUser.name;
        this.updateSupportStats();
        this.loadSupportTickets();
        
        // Resetar flags e adicionar event listeners
        this.supportLogoutListenerAdded = false;
        this.cepButtonListenerAdded = false;
        this.zipcodeListenerAdded = false;
        this.formListenerAdded = false;
        
        // Usar setTimeout para garantir que o DOM está pronto
        setTimeout(() => {
            this.setupDashboardListeners();
        }, 100);
    }

    showAdminDashboard() {
        this.showPage('adminDashboard');
        document.getElementById('adminUserName').textContent = this.currentUser.name;
        this.updateAdminStats();
        this.loadAdminTickets();
        this.loadUsers();
        this.loadCharts();
        this.populateUserFilter();
        
        // Resetar flags e adicionar event listeners
        this.adminLogoutListenerAdded = false;
        this.cepButtonListenerAdded = false;
        this.zipcodeListenerAdded = false;
        this.formListenerAdded = false;
        
        // Usar setTimeout para garantir que o DOM está pronto
        setTimeout(() => {
            this.setupDashboardListeners();
        }, 100);
    }

    // Page Management
    showPage(pageId) {
        document.querySelectorAll('.page').forEach(page => page.classList.remove('active'));
        document.getElementById(pageId).classList.add('active');
    }

    updateNavigation() {
        const navMenu = document.getElementById('navMenu');
        if (this.currentUser) {
            navMenu.innerHTML = `
                <li><span class="nav-link">Bem-vindo, ${this.currentUser.name}</span></li>
                <li><a href="#" class="nav-link" onclick="helpDesk.logout(); return false;">Sair</a></li>
            `;
        } else {
            navMenu.innerHTML = '<li><a href="#" class="nav-link active" data-page="login">Login</a></li>';
        }
    }

    // CEP Search
    async searchCep() {
        console.log('=== INICIANDO BUSCA DE CEP ===');
        
        const zipcodeInput = document.getElementById('zipcode');
        console.log('Campo zipcode encontrado:', !!zipcodeInput);
        if (!zipcodeInput) {
            console.error('ERRO: Campo de CEP não encontrado');
            alert('Erro: Campo de CEP não encontrado. Recarregue a página.');
            return;
        }
        
        const zipcode = zipcodeInput.value.replace(/\D/g, '');
        console.log('CEP digitado (após limpeza):', zipcode);
        console.log('Tamanho do CEP:', zipcode.length);
        
        if (zipcode.length !== 8) {
            console.log('ERRO: CEP deve conter 8 dígitos, mas tem', zipcode.length);
            alert('CEP deve conter 8 dígitos');
            return;
        }
        
        // Mostrar feedback de carregamento
        const searchBtn = document.getElementById('searchCepBtn');
        console.log('Botão searchCepBtn encontrado:', !!searchBtn);
        const originalText = searchBtn ? searchBtn.textContent : '';
        if (searchBtn) {
            searchBtn.textContent = 'Buscando...';
            searchBtn.disabled = true;
        }
        
        try {
            console.log('Fazendo requisição para ViaCEP...');
            const url = `https://viacep.com.br/ws/${zipcode}/json/`;
            console.log('URL completa:', url);
            
            const response = await fetch(url);
            console.log('Status da resposta:', response.status);
            console.log('Headers da resposta:', [...response.headers.entries()]);
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('Dados recebidos do ViaCEP:', data);
            console.log('Tipo dos dados:', typeof data);
            console.log('Tem erro?', data.erro);
            
            if (data.erro) {
                console.log('ERRO: CEP não encontrado na base do ViaCEP');
                alert('CEP não encontrado. Verifique se o CEP está correto.');
                if (searchBtn) {
                    searchBtn.textContent = originalText;
                    searchBtn.disabled = false;
                }
                return;
            }
            
            // Preencher campos de endereço
            const addressInput = document.getElementById('address');
            const cityInput = document.getElementById('city');
            const stateInput = document.getElementById('state');
            
            console.log('=== VERIFICANDO CAMPOS DE DESTINO ===');
            console.log('Campo address encontrado:', !!addressInput);
            console.log('Campo city encontrado:', !!cityInput);
            console.log('Campo state encontrado:', !!stateInput);
            
            console.log('=== PREENCHENDO CAMPOS ===');
            
            if (addressInput) {
                let endereco = data.logradouro || '';
                console.log('Logradouro:', data.logradouro);
                if (data.complemento) {
                    endereco += ', ' + data.complemento;
                    console.log('Complemento adicionado:', data.complemento);
                }
                if (data.bairro) {
                    endereco += ' - ' + data.bairro;
                    console.log('Bairro adicionado:', data.bairro);
                }
                
                // Forçar o preenchimento do campo
                const enderecoFinal = endereco.trim();
                console.log('Endereço final a ser preenchido:', enderecoFinal);
                
                // Remover readonly temporariamente para permitir preenchimento
                const wasReadonly = addressInput.hasAttribute('readonly');
                if (wasReadonly) {
                    addressInput.removeAttribute('readonly');
                    console.log('Atributo readonly removido temporariamente');
                }
                
                // Tentar diferentes métodos de preenchimento
                addressInput.value = enderecoFinal;
                addressInput.setAttribute('value', enderecoFinal);
                
                // Disparar eventos para garantir que o valor seja reconhecido
                addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                addressInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Restaurar readonly se estava ativo
                if (wasReadonly) {
                    addressInput.setAttribute('readonly', 'readonly');
                    console.log('Atributo readonly restaurado');
                }
                
                console.log('Valor após preenchimento:', addressInput.value);
                console.log('Valor do atributo value:', addressInput.getAttribute('value'));
                
                // Verificar se o campo foi realmente preenchido
                if (addressInput.value === enderecoFinal) {
                    console.log('SUCESSO: Campo de endereço preenchido corretamente');
                } else {
                    console.error('ERRO: Campo de endereço não foi preenchido corretamente');
                    console.error('Esperado:', enderecoFinal);
                    console.error('Atual:', addressInput.value);
                }
            } else {
                console.error('ERRO: Campo de endereço não encontrado');
            }
            
            if (cityInput) {
                const cidade = data.localidade || '';
                
                // Remover readonly temporariamente
                const wasReadonly = cityInput.hasAttribute('readonly');
                if (wasReadonly) {
                    cityInput.removeAttribute('readonly');
                }
                
                cityInput.value = cidade;
                cityInput.setAttribute('value', cidade);
                cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                cityInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Restaurar readonly
                if (wasReadonly) {
                    cityInput.setAttribute('readonly', 'readonly');
                }
                
                console.log('Cidade preenchida:', cityInput.value);
            } else {
                console.error('ERRO: Campo de cidade não encontrado');
            }
            
            if (stateInput) {
                const estado = data.uf || '';
                
                // Remover readonly temporariamente
                const wasReadonly = stateInput.hasAttribute('readonly');
                if (wasReadonly) {
                    stateInput.removeAttribute('readonly');
                }
                
                stateInput.value = estado;
                stateInput.setAttribute('value', estado);
                stateInput.dispatchEvent(new Event('input', { bubbles: true }));
                stateInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Restaurar readonly
                if (wasReadonly) {
                    stateInput.setAttribute('readonly', 'readonly');
                }
                
                console.log('Estado preenchido:', stateInput.value);
            } else {
                console.error('ERRO: Campo de estado não encontrado');
            }
            
            console.log('=== SUCESSO: CEP encontrado e campos preenchidos! ===');
            
        } catch (error) {
            console.error('ERRO na busca de CEP:', error);
            console.error('Stack trace:', error.stack);
            alert('Erro ao buscar CEP. Verifique sua conexão com a internet.\n\nDetalhes: ' + error.message);
        } finally {
            // Restaurar botão
            if (searchBtn) {
                searchBtn.textContent = originalText;
                searchBtn.disabled = false;
            }
            console.log('=== FIM DA BUSCA DE CEP ===');
        }
    }

    // Ticket Management
    async handleNewTicket(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const ticketData = {
            title: formData.get('title'),
            description: formData.get('description'),
            priority: formData.get('priority'),
            category: formData.get('category'),
            requester_name: formData.get('requester_name'),
            requester_registration: formData.get('requester_registration'),
            unit_sigla: formData.get('unit_sigla'),
            zipcode: formData.get('zipcode'),
            address: formData.get('address'),
            city: formData.get('city'),
            state: formData.get('state')
        };
        try {
            const result = await this.apiCall('tickets.php?action=create', 'POST', ticketData);
            alert('Ticket criado com sucesso!');
            e.target.reset();
            
            // Recarregar tickets baseado no tipo de usuário
            if (this.currentUser) {
                switch(this.currentUser.type) {
                    case 'user':
                        this.loadUserTickets();
                        break;
                    case 'support':
                        this.loadSupportTickets();
                        break;
                    case 'admin':
                        this.loadAdminTickets();
                        break;
                }
            }
        } catch (error) {
            alert('Erro ao criar ticket: ' + error.message);
        }
    }

    updateTicketStatus(ticketId, status, assignedTo = null) {
        const ticket = this.tickets.find(t => t.id === ticketId);
        if (ticket) {
            ticket.status = status;
            ticket.updatedAt = new Date().toISOString();
            
            if (status === 'fechado') {
                ticket.closedAt = new Date().toISOString();
            }
            
            if (assignedTo) {
                const user = this.users.find(u => u.id === assignedTo);
                ticket.assignedTo = assignedTo;
                ticket.assignedToName = user ? user.name : null;
            }
            
            this.saveTickets();
            this.updateStats();
        }
    }

    // Statistics
    updateUserStats() {
        // Estatísticas serão atualizadas via API
        // Por enquanto, apenas limpar os valores
        document.getElementById('userOpenTickets').textContent = '0';
        document.getElementById('userPendingTickets').textContent = '0';
        document.getElementById('userClosedTickets').textContent = '0';
    }

    updateSupportStats() {
        // Estatísticas serão atualizadas via API
        // Por enquanto, apenas limpar os valores
        document.getElementById('supportOpenTickets').textContent = '0';
        document.getElementById('supportAssignedTickets').textContent = '0';
        document.getElementById('supportInProgressTickets').textContent = '0';
        document.getElementById('supportClosedTickets').textContent = '0';
    }

    updateAdminStats() {
        // Estatísticas serão atualizadas via API
        // Por enquanto, apenas limpar os valores
        document.getElementById('adminTotalTickets').textContent = '0';
        document.getElementById('adminTotalUsers').textContent = '0';
        document.getElementById('adminSupportUsers').textContent = '0';
        document.getElementById('adminAvgResolutionTime').textContent = '0h';
    }

    updateStats() {
        if (this.currentUser) {
            switch(this.currentUser.type) {
                case 'user':
                    this.updateUserStats();
                    this.loadUserTickets();
                    break;
                case 'support':
                    this.updateSupportStats();
                    this.loadSupportTickets();
                    break;
                case 'admin':
                    this.updateAdminStats();
                    this.loadAdminTickets();
                    break;
            }
        }
    }

    // Ticket Loading and Display
    async loadUserTickets() {
        try {
            const result = await this.apiCall('tickets_simple.php');
            this.displayTickets(result.data, 'userTicketsBody');
            this.updateTicketCounters(result.data);
        } catch (error) {
            console.error('Erro ao carregar tickets:', error);
        }
    }

    async loadSupportTickets() {
        try {
            const result = await this.apiCall('tickets_simple.php');
            this.displayTickets(result.data, 'supportTicketsBody');
            this.updateTicketCounters(result.data);
        } catch (error) {
            console.error('Erro ao carregar tickets:', error);
        }
    }

    async loadAdminTickets() {
        try {
            const result = await this.apiCall('tickets_simple.php');
            this.displayTickets(result.data, 'adminTicketsBody');
            this.updateTicketCounters(result.data);
        } catch (error) {
            console.error('Erro ao carregar tickets:', error);
        }
    }

    // Atualizar contadores de tickets
    updateTicketCounters(tickets) {
        if (!tickets || !Array.isArray(tickets)) return;
        
        // Contar tickets por status
        const counters = {
            open: 0,
            in_progress: 0,
            resolved: 0,
            closed: 0,
            total: tickets.length
        };
        
        tickets.forEach(ticket => {
            if (ticket.status === 'open') counters.open++;
            else if (ticket.status === 'in_progress') counters.in_progress++;
            else if (ticket.status === 'resolved') counters.resolved++;
            else if (ticket.status === 'closed') counters.closed++;
        });
        
        // Atualizar elementos na tela
        this.updateCounterElement('userOpenTickets', counters.open);
        this.updateCounterElement('userPendingTickets', counters.in_progress);
        this.updateCounterElement('userResolvedTickets', counters.resolved);
        this.updateCounterElement('userClosedTickets', counters.closed);
        this.updateCounterElement('userTotalTickets', counters.total);
        
        // Contadores para suporte
        this.updateCounterElement('supportOpenTickets', counters.open);
        this.updateCounterElement('supportPendingTickets', counters.in_progress);
        this.updateCounterElement('supportResolvedTickets', counters.resolved);
        this.updateCounterElement('supportClosedTickets', counters.closed);
        this.updateCounterElement('supportTotalTickets', counters.total);
        
        // Contadores para admin
        this.updateCounterElement('adminOpenTickets', counters.open);
        this.updateCounterElement('adminPendingTickets', counters.in_progress);
        this.updateCounterElement('adminResolvedTickets', counters.resolved);
        this.updateCounterElement('adminClosedTickets', counters.closed);
        this.updateCounterElement('adminTotalTickets', counters.total);
        
        console.log('Contadores atualizados:', counters);
    }

    updateCounterElement(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value;
        }
    }

    displayTickets(tickets, containerId) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.warn('Container não encontrado:', containerId);
            return;
        }
        
        console.log('Exibindo tickets:', tickets, 'no container:', containerId);
        
        if (!tickets || tickets.length === 0) {
            container.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum ticket encontrado.</td></tr>';
            return;
        }
        
        // Determinar se é perfil suporte
        const isSupport = containerId === 'supportTicketsBody';
        
        // Preencher tbody com os tickets
        container.innerHTML = tickets.map(ticket => `
            <tr>
                <td>#${ticket.id}</td>
                <td>${ticket.title}</td>
                <td>
                    ${isSupport ? `
                        <select class="status-select status-${ticket.status}" onchange="helpDesk.updateTicketStatusQuick(${ticket.id}, this.value)">
                            <option value="open" ${ticket.status === 'open' ? 'selected' : ''}>Aberto</option>
                            <option value="in_progress" ${ticket.status === 'in_progress' ? 'selected' : ''}>Em Andamento</option>
                            <option value="resolved" ${ticket.status === 'resolved' ? 'selected' : ''}>Resolvido</option>
                            <option value="closed" ${ticket.status === 'closed' ? 'selected' : ''}>Fechado</option>
                        </select>
                    ` : `
                        <span class="status status-${ticket.status}">${this.getStatusText(ticket.status)}</span>
                    `}
                </td>
                <td><span class="priority priority-${ticket.priority}">${this.getPriorityText(ticket.priority)}</span></td>
                <td>${new Date(ticket.created_at).toLocaleDateString('pt-BR')}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="helpDesk.viewTicket(${ticket.id})">Ver</button>
                    ${this.currentUser && this.currentUser.type !== 'user' ? `
                        <button class="btn btn-sm btn-secondary" onclick="helpDesk.editTicket(${ticket.id})">Editar</button>
                    ` : ''}
                </td>
            </tr>
        `).join('');
    }

    // Atualizar status rapidamente (para suporte)
    async updateTicketStatusQuick(ticketId, newStatus) {
        try {
            console.log(`Atualizando ticket ${ticketId} para status ${newStatus}`);
            
            const result = await this.apiCall(`ticket_update.php?id=${ticketId}`, 'PUT', {
                status: newStatus
            });
            
            if (result.success) {
                // Recarregar tickets do suporte
                this.loadSupportTickets();
            } else {
                alert('Erro ao atualizar status: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao atualizar status:', error);
            alert('Erro ao atualizar status: ' + error.message);
        }
    }

    getStatusText(status) {
        const statusMap = {
            'open': 'Aberto',
            'in_progress': 'Em Andamento',
            'resolved': 'Resolvido',
            'closed': 'Fechado'
        };
        return statusMap[status] || status;
    }

    getPriorityText(priority) {
        const priorityMap = {
            'low': 'Baixa',
            'medium': 'Média',
            'high': 'Alta',
            'urgent': 'Urgente'
        };
        return priorityMap[priority] || priority;
    }

    async viewTicket(ticketId) {
        try {
            const result = await this.apiCall(`ticket_view.php?id=${ticketId}`);
            
            if (result.success && result.data) {
                const ticket = result.data;
                this.showTicketModal(ticket);
            } else {
                alert('Erro ao carregar ticket: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao visualizar ticket:', error);
            alert('Erro ao carregar ticket: ' + error.message);
        }
    }

    async editTicket(ticketId) {
        try {
            const result = await this.apiCall(`ticket_view.php?id=${ticketId}`);
            
            if (result.success && result.data) {
                const ticket = result.data;
                this.showEditTicketModal(ticket);
            } else {
                alert('Erro ao carregar ticket: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao editar ticket:', error);
            alert('Erro ao carregar ticket: ' + error.message);
        }
    }

    showTicketModal(ticket) {
        console.log('Ticket recebido:', ticket);
        
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Ticket #${ticket.id || 'N/A'} - ${ticket.title || 'N/A'}</h2>
                    <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="ticket-details">
                        <p><strong>Status:</strong> <span class="status status-${ticket.status || 'unknown'}">${this.getStatusText(ticket.status)}</span></p>
                        <p><strong>Prioridade:</strong> <span class="priority priority-${ticket.priority || 'unknown'}">${this.getPriorityText(ticket.priority)}</span></p>
                        <p><strong>Categoria:</strong> ${ticket.category || 'N/A'}</p>
                        <p><strong>Usuário:</strong> ${ticket.user_name || 'N/A'}</p>
                        <p><strong>Criado em:</strong> ${ticket.created_at ? new Date(ticket.created_at).toLocaleString('pt-BR') : 'N/A'}</p>
                        <p><strong>Atualizado em:</strong> ${ticket.updated_at ? new Date(ticket.updated_at).toLocaleString('pt-BR') : 'N/A'}</p>
                        <p><strong>Descrição:</strong></p>
                        <div class="ticket-description">${ticket.description || 'N/A'}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Fechar</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    showEditTicketModal(ticket) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.display = 'block';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Editar Ticket #${ticket.id}</h2>
                    <span class="close" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="editTicketForm">
                        <div class="form-group">
                            <label for="editTitle">Título:</label>
                            <input type="text" id="editTitle" value="${ticket.title}" required>
                        </div>
                        <div class="form-group">
                            <label for="editDescription">Descrição:</label>
                            <textarea id="editDescription" rows="4" required>${ticket.description}</textarea>
                        </div>
                        <div class="form-group">
                            <label for="editPriority">Prioridade:</label>
                            <select id="editPriority">
                                <option value="low" ${ticket.priority === 'low' ? 'selected' : ''}>Baixa</option>
                                <option value="medium" ${ticket.priority === 'medium' ? 'selected' : ''}>Média</option>
                                <option value="high" ${ticket.priority === 'high' ? 'selected' : ''}>Alta</option>
                                <option value="urgent" ${ticket.priority === 'urgent' ? 'selected' : ''}>Urgente</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Status:</label>
                            <select id="editStatus">
                                <option value="open" ${ticket.status === 'open' ? 'selected' : ''}>Aberto</option>
                                <option value="in_progress" ${ticket.status === 'in_progress' ? 'selected' : ''}>Em Andamento</option>
                                <option value="resolved" ${ticket.status === 'resolved' ? 'selected' : ''}>Resolvido</option>
                                <option value="closed" ${ticket.status === 'closed' ? 'selected' : ''}>Fechado</option>
                            </select>
                        </div>
                        ${this.currentUser && (this.currentUser.type === 'admin' || this.currentUser.type === 'manager') ? `
                        <div class="form-row">
                            <div class="form-group">
                                <label for="editTechnicianValue">Valor do Técnico (R$):</label>
                                <input type="number" id="editTechnicianValue" step="0.01" value="${ticket.technician_value || ''}" placeholder="0.00">
                            </div>
                            <div class="form-group">
                                <label for="editClosingValue">Valor Final (R$):</label>
                                <input type="number" id="editClosingValue" step="0.01" value="${ticket.closing_value || ''}" placeholder="0.00">
                            </div>
                        </div>
                        ` : ''}
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="helpDesk.saveTicketEdit(${ticket.id})">Salvar</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Cancelar</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    async saveTicketEdit(ticketId) {
        try {
            const title = document.getElementById('editTitle').value;
            const description = document.getElementById('editDescription').value;
            const priority = document.getElementById('editPriority').value;
            const status = document.getElementById('editStatus').value;
            
            const ticketData = {
                title: title,
                description: description,
                priority: priority,
                status: status
            };
            
            // Adicionar campos de valores se for admin ou gerente
            if (this.currentUser && (this.currentUser.type === 'admin' || this.currentUser.type === 'manager')) {
                const technicianValue = document.getElementById('editTechnicianValue');
                const closingValue = document.getElementById('editClosingValue');
                
                if (technicianValue) {
                    ticketData.technician_value = technicianValue.value || null;
                }
                if (closingValue) {
                    ticketData.closing_value = closingValue.value || null;
                }
            }
            
            const result = await this.apiCall(`ticket_update.php?id=${ticketId}`, 'PUT', ticketData);
            
            if (result.success) {
                alert('Ticket atualizado com sucesso!');
                document.querySelector('.modal').remove();
                
                // Recarregar tickets
                if (this.currentUser) {
                    switch(this.currentUser.type) {
                        case 'user':
                            this.loadUserTickets();
                            break;
                        case 'support':
                            this.loadSupportTickets();
                            break;
                        case 'admin':
                            this.loadAdminTickets();
                            break;
                    }
                }
            } else {
                alert('Erro ao atualizar ticket: ' + (result.message || 'Erro desconhecido'));
            }
        } catch (error) {
            console.error('Erro ao salvar edição:', error);
            alert('Erro ao salvar ticket: ' + error.message);
        }
    }

    filterSupportTickets() {
        const statusFilter = document.getElementById('statusFilter').value;
        const priorityFilter = document.getElementById('priorityFilter').value;
        
        let filteredTickets = [...this.tickets];
        
        if (statusFilter) {
            filteredTickets = filteredTickets.filter(t => t.status === statusFilter);
        }
        
        if (priorityFilter) {
            filteredTickets = filteredTickets.filter(t => t.priority === priorityFilter);
        }
        
        filteredTickets.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        this.renderTickets(filteredTickets, 'supportTicketsBody', 'support');
    }

    filterAdminTickets() {
        const statusFilter = document.getElementById('adminStatusFilter').value;
        const priorityFilter = document.getElementById('adminPriorityFilter').value;
        const userFilter = document.getElementById('adminUserFilter').value;
        
        let filteredTickets = [...this.tickets];
        
        if (statusFilter) {
            filteredTickets = filteredTickets.filter(t => t.status === statusFilter);
        }
        
        if (priorityFilter) {
            filteredTickets = filteredTickets.filter(t => t.priority === priorityFilter);
        }
        
        if (userFilter) {
            filteredTickets = filteredTickets.filter(t => t.userId == userFilter);
        }
        
        filteredTickets.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
        this.renderTickets(filteredTickets, 'adminTicketsBody', 'admin');
    }

    renderTickets(tickets, containerId, userType) {
        const container = document.getElementById(containerId);
        container.innerHTML = '';
        
        tickets.forEach(ticket => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>#${ticket.id}</td>
                <td>${ticket.title}</td>
                ${userType === 'user' ? '' : `<td>${ticket.userName}</td>`}
                <td><span class="status-badge status-${ticket.status}">${this.getStatusText(ticket.status)}</span></td>
                <td><span class="priority-badge priority-${ticket.priority}">${this.getPriorityText(ticket.priority)}</span></td>
                ${userType === 'admin' ? `<td>${ticket.assignedToName || 'Não atribuído'}</td>` : ''}
                <td>${this.formatDate(ticket.createdAt)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-info" onclick="helpDesk.showTicketDetails(${ticket.id})">
                            <i class="fas fa-eye"></i> Ver
                        </button>
                        ${this.getTicketActions(ticket, userType)}
                    </div>
                </td>
            `;
            container.appendChild(row);
        });
    }

    getTicketActions(ticket, userType) {
        let actions = '';
        
        if (userType === 'support' || userType === 'admin') {
            if (ticket.status === 'aberto') {
                actions += `<button class="btn btn-sm btn-warning" onclick="helpDesk.updateTicketStatus(${ticket.id}, 'em_andamento', ${this.currentUser.id})">
                    <i class="fas fa-play"></i> Iniciar
                </button>`;
            } else if (ticket.status === 'em_andamento') {
                actions += `<button class="btn btn-sm btn-success" onclick="helpDesk.updateTicketStatus(${ticket.id}, 'fechado')">
                    <i class="fas fa-check"></i> Fechar
                </button>`;
            }
        }
        
        if (userType === 'admin') {
            if (ticket.status !== 'fechado') {
                actions += `<button class="btn btn-sm btn-danger" onclick="helpDesk.deleteTicket(${ticket.id})">
                    <i class="fas fa-trash"></i> Excluir
                </button>`;
            }
        }
        
        return actions;
    }

    updateTicketStatus(ticketId, status, assignedTo = null) {
        const ticket = this.tickets.find(t => t.id === ticketId);
        if (ticket) {
            ticket.status = status;
            ticket.updatedAt = new Date().toISOString();
            
            if (status === 'fechado') {
                ticket.closedAt = new Date().toISOString();
            }
            
            if (assignedTo) {
                const user = this.users.find(u => u.id === assignedTo);
                ticket.assignedTo = assignedTo;
                ticket.assignedToName = user ? user.name : null;
            }
            
            this.saveTickets();
            this.updateStats();
            
            if (this.currentUser.type === 'support') {
                this.loadSupportTickets();
            } else if (this.currentUser.type === 'admin') {
                this.loadAdminTickets();
            } else if (this.currentUser.type === 'user') {
                this.loadUserTickets();
            }
            
            alert(`Chamado ${status === 'em_andamento' ? 'iniciado' : 'fechado'} com sucesso!`);
        }
    }

    deleteTicket(ticketId) {
        if (confirm('Tem certeza que deseja excluir este chamado?')) {
            this.tickets = this.tickets.filter(t => t.id !== ticketId);
            this.saveTickets();
            this.updateStats();
            this.loadAdminTickets();
        }
    }

    showTicketDetails(ticketId) {
        const ticket = this.tickets.find(t => t.id === ticketId);
        if (!ticket) return;
        
        document.getElementById('modalTitle').textContent = `Chamado #${ticket.id}`;
        document.getElementById('modalBody').innerHTML = `
            <div class="ticket-details">
                <div class="detail-row">
                    <strong>Título:</strong> ${ticket.title}
                </div>
                <div class="detail-row">
                    <strong>Descrição:</strong> ${ticket.description}
                </div>
                <div class="detail-row">
                    <strong>Categoria:</strong> ${this.getCategoryText(ticket.category)}
                </div>
                <div class="detail-row">
                    <strong>Prioridade:</strong> <span class="priority-badge priority-${ticket.priority}">${this.getPriorityText(ticket.priority)}</span>
                </div>
                <div class="detail-row">
                    <strong>Status:</strong> <span class="status-badge status-${ticket.status}">${this.getStatusText(ticket.status)}</span>
                </div>
                <div class="detail-row">
                    <strong>Usuário:</strong> ${ticket.userName}
                </div>
                ${ticket.assignedToName ? `<div class="detail-row"><strong>Atribuído a:</strong> ${ticket.assignedToName}</div>` : ''}
                <div class="detail-row">
                    <strong>Criado em:</strong> ${this.formatDate(ticket.createdAt)}
                </div>
                <div class="detail-row">
                    <strong>Última atualização:</strong> ${this.formatDate(ticket.updatedAt)}
                </div>
                ${ticket.closedAt ? `<div class="detail-row"><strong>Fechado em:</strong> ${this.formatDate(ticket.closedAt)}</div>` : ''}
            </div>
        `;
        
        document.getElementById('ticketModal').style.display = 'block';
    }

    // User Management
    showUserModal(userId = null) {
        document.getElementById('userModalTitle').textContent = userId ? 'Editar Usuário' : 'Adicionar Usuário';
        document.getElementById('userModal').style.display = 'block';
        
        if (userId) {
            const user = this.users.find(u => u.id === userId);
            if (user) {
                document.getElementById('userName').value = user.name;
                document.getElementById('userEmail').value = user.email;
                document.getElementById('userType').value = user.type;
                document.getElementById('userPassword').value = '';
                document.getElementById('userPassword').required = false;
            }
        } else {
            document.getElementById('userForm').reset();
            document.getElementById('userPassword').required = true;
        }
    }

    handleUserForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        const userData = {
            name: formData.get('name'),
            client_name: formData.get('client_name'),
            email: formData.get('email'),
            password: formData.get('password'),
            type: formData.get('type'),
            username: formData.get('email').split('@')[0], // Generate username from email
            active: true,
            createdAt: new Date().toISOString()
        };
        
        // Check if editing existing user
        const existingUser = Array.from(e.target.querySelectorAll('input')).some(input => input.value && input.dataset.userId);
        
        if (existingUser) {
            // Update existing user
            const userId = parseInt(e.target.dataset.userId);
            const userIndex = this.users.findIndex(u => u.id === userId);
            if (userIndex !== -1) {
                this.users[userIndex] = { ...this.users[userIndex], ...userData };
            }
        } else {
            // Create new user
            userData.id = this.userIdCounter++;
            this.users.push(userData);
        }
        
        this.saveUsers();
        this.loadUsers();
        this.closeModals();
        
        alert('Usuário salvo com sucesso!');
    }

    loadUsers() {
        const users = this.users.filter(u => u.type !== 'admin'); // Don't show other admins
        
        const container = document.getElementById('usersBody');
        container.innerHTML = '';
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.name}</td>
                <td>${user.email}</td>
                <td>${this.getUserTypeText(user.type)}</td>
                <td><span class="status-badge ${user.active ? 'status-fechado' : 'status-aberto'}">${user.active ? 'Ativo' : 'Inativo'}</span></td>
                <td>${this.formatDate(user.createdAt)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-info" onclick="helpDesk.showUserModal(${user.id})">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="helpDesk.toggleUserStatus(${user.id})">
                            <i class="fas fa-toggle-${user.active ? 'on' : 'off'}"></i> ${user.active ? 'Desativar' : 'Ativar'}
                        </button>
                    </div>
                </td>
            `;
            container.appendChild(row);
        });
    }

    toggleUserStatus(userId) {
        const user = this.users.find(u => u.id === userId);
        if (user) {
            user.active = !user.active;
            this.saveUsers();
            this.loadUsers();
        }
    }

    populateUserFilter() {
        const filter = document.getElementById('adminUserFilter');
        if (!filter) {
            console.warn('Filtro adminUserFilter não encontrado');
            return;
        }
        
        // Limpar opções existentes (exceto a primeira)
        while (filter.children.length > 1) {
            filter.removeChild(filter.lastChild);
        }
        
        // Por enquanto, não carregar usuários via API
        // Isso será implementado quando a API de usuários estiver pronta
        console.log('Filtro de usuários será implementado quando a API estiver pronta');
    }

    // Charts and Reports
    loadCharts() {
        // Gráficos serão implementados quando a API estiver pronta
        console.log('Gráficos serão implementados quando a API estiver pronta');
    }

    loadStatusChart() {
        const ctx = document.getElementById('statusChart').getContext('2d');
        const statusCounts = {
            'aberto': this.tickets.filter(t => t.status === 'aberto').length,
            'em_andamento': this.tickets.filter(t => t.status === 'em_andamento').length,
            'fechado': this.tickets.filter(t => t.status === 'fechado').length
        };
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Abertos', 'Em Andamento', 'Fechados'],
                datasets: [{
                    data: [statusCounts.aberto, statusCounts.em_andamento, statusCounts.fechado],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }

    loadPriorityChart() {
        const ctx = document.getElementById('priorityChart').getContext('2d');
        const priorityCounts = {
            'baixa': this.tickets.filter(t => t.priority === 'baixa').length,
            'media': this.tickets.filter(t => t.priority === 'media').length,
            'alta': this.tickets.filter(t => t.priority === 'alta').length,
            'critica': this.tickets.filter(t => t.priority === 'critica').length
        };
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Baixa', 'Média', 'Alta', 'Crítica'],
                datasets: [{
                    data: [priorityCounts.baixa, priorityCounts.media, priorityCounts.alta, priorityCounts.critica],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#dc2626']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    loadAgentChart() {
        const ctx = document.getElementById('agentChart').getContext('2d');
        const supportUsers = this.users.filter(u => u.type === 'support');
        const agentStats = supportUsers.map(user => ({
            name: user.name,
            tickets: this.tickets.filter(t => t.assignedTo === user.id).length
        }));
        
        new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: agentStats.map(a => a.name),
                datasets: [{
                    data: agentStats.map(a => a.tickets),
                    backgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    updateResolutionTimeStats() {
        const closedTickets = this.tickets.filter(t => t.status === 'fechado' && t.closedAt);
        
        if (closedTickets.length > 0) {
            const resolutionTimes = closedTickets.map(ticket => {
                const created = new Date(ticket.createdAt);
                const closed = new Date(ticket.closedAt);
                return (closed - created) / (1000 * 60 * 60); // Convert to hours
            });
            
            const avg = resolutionTimes.reduce((sum, time) => sum + time, 0) / resolutionTimes.length;
            const min = Math.min(...resolutionTimes);
            const max = Math.max(...resolutionTimes);
            
            document.getElementById('avgResolution').textContent = Math.round(avg) + 'h';
            document.getElementById('minResolution').textContent = Math.round(min) + 'h';
            document.getElementById('maxResolution').textContent = Math.round(max) + 'h';
        }
    }

    // Tab Management
    switchTab(tabName) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        document.getElementById(`${tabName}Tab`).classList.add('active');
        
        if (tabName === 'reports') {
            this.loadCharts();
        }
    }

    // Utility Functions
    getStatusText(status) {
        const statusMap = {
            'aberto': 'Aberto',
            'em_andamento': 'Em Andamento',
            'fechado': 'Fechado'
        };
        return statusMap[status] || status;
    }

    getPriorityText(priority) {
        const priorityMap = {
            'baixa': 'Baixa',
            'media': 'Média',
            'alta': 'Alta',
            'critica': 'Crítica'
        };
        return priorityMap[priority] || priority;
    }

    getCategoryText(category) {
        const categoryMap = {
            'hardware': 'Hardware',
            'software': 'Software',
            'rede': 'Rede',
            'email': 'E-mail',
            'outros': 'Outros'
        };
        return categoryMap[category] || category;
    }

    getUserTypeText(type) {
        const typeMap = {
            'user': 'Usuário Padrão',
            'support': 'Suporte',
            'admin': 'Administrador'
        };
        return typeMap[type] || type;
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Modal Management
    closeModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }

    // Mobile Navigation
    toggleMobileNav() {
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        
        navToggle.classList.toggle('active');
        navMenu.classList.toggle('active');
    }

    // Data Management
    saveTickets() {
        localStorage.setItem('helpDeskTickets', JSON.stringify(this.tickets));
    }

    loadTickets() {
        const saved = localStorage.getItem('helpDeskTickets');
        return saved ? JSON.parse(saved) : [];
    }

    saveUsers() {
        localStorage.setItem('helpDeskUsers', JSON.stringify(this.users));
    }

    async loadUsers() {
        try {
            const result = await this.apiCall('users.php?action=list');
            if (result.success && result.data) {
                this.displayUsers(result.data);
            }
        } catch (error) {
            console.error('Erro ao carregar usuários:', error);
        }
    }

    displayUsers(users) {
        const container = document.getElementById('adminUsersList');
        if (!container) {
            console.warn('Container adminUsersList não encontrado');
            return;
        }
        
        if (!users || users.length === 0) {
            container.innerHTML = '<p>Nenhum usuário encontrado.</p>';
            return;
        }
        
        container.innerHTML = users.map(user => `
            <tr>
                <td>${user.id}</td>
                <td>${user.name}</td>
                <td>${user.email}</td>
                <td>${user.username}</td>
                <td><span class="badge badge-${user.type}">${this.getUserTypeText(user.type)}</span></td>
                <td>${user.is_active ? 'Ativo' : 'Inativo'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="helpDesk.editUser(${user.id})">Editar</button>
                    <button class="btn btn-sm btn-danger" onclick="helpDesk.deleteUser(${user.id})">Excluir</button>
                </td>
            </tr>
        `).join('');
    }

    getUserTypeText(type) {
        const typeMap = {
            'user': 'Usuário',
            'support': 'Suporte',
            'admin': 'Administrador'
        };
        return typeMap[type] || type;
    }

    // Demo Data
    populateDemoData() {
        if (this.users.length === 0) {
            this.users = [
                {
                    id: 1,
                    name: 'Usuário Padrão',
                    email: 'usuario@controllit.com.br',
                    username: 'usuario',
                    password: 'senha123',
                    type: 'user',
                    active: true,
                    createdAt: new Date().toISOString()
                },
                {
                    id: 2,
                    name: 'Agente de Suporte',
                    email: 'suporte@controllit.com.br',
                    username: 'suporte',
                    password: 'senha123',
                    type: 'support',
                    active: true,
                    createdAt: new Date().toISOString()
                },
                {
                    id: 3,
                    name: 'Administrador',
                    email: 'admin@controllit.com.br',
                    username: 'admin',
                    password: 'senha123',
                    type: 'admin',
                    active: true,
                    createdAt: new Date().toISOString()
                }
            ];
            this.saveUsers();
        }
        
        if (this.tickets.length === 0) {
            this.tickets = [
                {
                    id: 1,
                    title: 'Problema com impressora',
                    description: 'A impressora não está funcionando corretamente. Aparece erro de papel.',
                    category: 'hardware',
                    priority: 'media',
                    status: 'aberto',
                    userId: 1,
                    userName: 'Usuário Padrão',
                    assignedTo: null,
                    assignedToName: null,
                    createdAt: new Date(Date.now() - 86400000).toISOString(),
                    updatedAt: new Date(Date.now() - 86400000).toISOString(),
                    closedAt: null
                },
                {
                    id: 2,
                    title: 'Sistema lento',
                    description: 'O sistema está muito lento, demora para abrir os programas.',
                    category: 'software',
                    priority: 'alta',
                    status: 'em_andamento',
                    userId: 1,
                    userName: 'Usuário Padrão',
                    assignedTo: 2,
                    assignedToName: 'Agente de Suporte',
                    createdAt: new Date(Date.now() - 172800000).toISOString(),
                    updatedAt: new Date(Date.now() - 3600000).toISOString(),
                    closedAt: null
                },
                {
                    id: 3,
                    title: 'Acesso ao email',
                    description: 'Não consigo acessar minha conta de email corporativo.',
                    category: 'email',
                    priority: 'baixa',
                    status: 'fechado',
                    userId: 1,
                    userName: 'Usuário Padrão',
                    assignedTo: 2,
                    assignedToName: 'Agente de Suporte',
                    createdAt: new Date(Date.now() - 259200000).toISOString(),
                    updatedAt: new Date(Date.now() - 43200000).toISOString(),
                    closedAt: new Date(Date.now() - 43200000).toISOString()
                }
            ];
            this.saveTickets();
        }
    }
}

// Initialize the system
const helpDesk = new HelpDeskSystem();

// Check if user is already logged in
window.addEventListener('load', () => {
    const savedUser = localStorage.getItem('currentUser');
    if (savedUser) {
        const user = JSON.parse(savedUser);
        helpDesk.currentUser = user;
        helpDesk.login(user);
    }
});
