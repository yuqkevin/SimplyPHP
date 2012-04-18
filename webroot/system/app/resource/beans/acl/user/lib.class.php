<?php
class LibAclUser extends LibAcl
{
    const    DNA_SYS         = 1;    // global system users. top privileges in global scope
    const    GROUP_SYS        = 1;    // system mantain group which has full access in global scope

    const    ENV_OPERATOR    = 'operator';

    const    DOMAIN_VERIFIED = '1';
    const    DOMAIN_UNVERIFIED = '0';

    const    COMP_PERMIT_ALLOW    = 'A';
    const    COMP_PERMIT_DENY    = 'D';

    const    EMAIL_VERIFIED = '1';
    const    EMAIL_UNVERIFIED = '0';

    protected $tbl_ini = 'LibAcl:';    // using LibAcl default db.tbl.ini
    function db_initial($passwd)
    {
        $this->status['error_code'] = 'INITIAL_FAILURE';
        $this->status['error'] = "Failed to initial AclUser database.";
        if (!$passwd) {
            $this->status['error'] = "You must set initial password for system account.";
            return false;
        }
        if ($this->tbl->account->read(self::DNA_SYS)) {
            $this->status['error'] = "System account already exists.";
            return false;
        }
        // domain
        $domain = array('id'=>1,'name'=>$this->env('DOMAIN'),'dna'=>self::DNA_SYS, 'status'=>self::DOMAIN_VERIFIED);
        if (!$domain_id=$this->tbl->domain->create($domain)) {
            $this->status['error'] = "Failed to create domain.";
            return false;
        }

        $group_id = self::GROUP_SYS;
        $group = array('id'=>$group_id,'dna'=>self::DNA_SYS,'domain'=>$domain_id,'name'=>'System');
        if (!$this->tbl->group->create($group)) {
            $this->status['error'] = "Failed to create group.";
            $this->tbl->domain->delete($domain_id);
            return false;
        }

        $user = array('id'=>self::DNA_SYS,'login'=>'administrator','group'=>$group_id,'domain'=>$domain_id,'dna'=>self::DNA_SYS,'pass'=>md5($passwd),'comments'=>"Init PWD:$passwd",'creator'=>0);
        if (!$this->tbl->account->create($user)) {
            $this->status['error'] = "Failed to create user.";
            $this->tbl->group->delete($group_id);
            $this->tbl->domain->delete($domain_id);
            return false;
        }
        return $this->sign_in('administrator', $passwd);
    }
    function sign_in($name, $pass)
    {
        $filter = array('login'=>$name);
        if ($rows=$this->tbl->account->search($filter)) {
            if ($rows[0]['pass']==md5($pass)) {
                $user = $rows[0];
                $user['group'] = $this->tbl->group->read($user['group']);
                $domain = $this->tbl->domain->read($user['group']['domain']);
                //check access domain
                if ($this->env('DOMAIN')!==$domain['name']) {
                    $pass = false;
                    // check user domain for sys group account
                    if ($user['group']['id']==self::GROUP_SYS) {
                        $domain = $this->tbl->domain->read($user['domain']);
                        $pass = (bool) $this->env('DOMAIN')==$domain['name'];
                    }
                    if (!$pass) {
                        $this->status['error_code'] = 'INVALID_ACCESS_DOMAIN';
                        $this->status['error'] = "User has no access on this domain.";
                        return false;
                    }
                }
                return $this->env(self::ENV_OPERATOR, $this->session_cookie('new', $user));
            }
            $this->status['error_code'] = 'LOGIN_PASS_NOT_MATCH';
            $this->status['error'] = "Login ID and password does not match.";
        } else {
            $this->status['error'] = "Login ID does not exist";
            $this->status['error_code'] = 'LOGIN_NOT_EXISTS';
        }
        return false;
    }
    function sign_out()
    {
        $this->env(self::ENV_OPERATOR,'');
        $this->session_hook('clear');
        return $this->session_cookie('close');
    }
    function info()
    {
        return  $this->session_cookie('get');
    }
    function session_verify($lifetime=0, $login=null, $pass=null, $cross_domain=false)
    {
        if (!$operator=$this->session_cookie('verify', $lifetime)) {
            if ($login&&$pass) {
                if ($operator=$this->sign_in($login, $pass, $cross_domain)) return $operator;
            } elseif (!$this->tbl->account->read(self::DNA_SYS)) {
                $this->status['error_code'] = 'NOT_INITIALIZED';
            }
            return false;
        } elseif ($login) {
            if ($operator['login']!==$login) {
                $this->status['error_code'] = 'LOGIN_SESSION_NOT_MATCH';
                $this->status['error'] = "Login ID does not match the current session.";
                return false;
            }
        }
        return $this->env(self::ENV_OPERATOR, $operator);
    }
    function search($filter=null, $suffix=null)
    {
        $operator = $this->info();
        $filter['id::>'] = 1; // Hide system root
        if (!isset($filter['dna'])&&$operator['dna']!=self::DNA_SYS) $filter['dna'] = $operator['dna'];
        $lines= $this->tbl->account->search($filter, $suffix);
        return $lines;
    }
    function account($act, $id=null, $param=null)
    {
        if (!($entry=$this->dna_verify('account', $id))&&!in_array($act,array('check','read'))) return false;
        $operator = $this->info();
        switch ($act) {
            case 'read':
                return $entry;
                break;
            case 'create':
                $param['dna'] = $operator['dna'];
                return $this->tbl->account->create($param);
                break;
            case 'modify':
                // Do not allow change dna for user's safty
                if (isset($param['dna'])&&$operator['dna']!=self::DNA_SYS) unset($param['dna']);
                return $this->tbl->account->update($id, $param);
                break;
            case 'primary':
                // only sys user user can set account to be a primary account
                if ($operator['dna']==self::DNA_SYS) {
                    return $this->tbl->account->update($id, array('dna'=>$entry['id']));
                }
                $this->status['error_code'] = 'NON_SYS_ACCOUNT';
                $this->status['error'] = "Only system account can change an account to be a primary account.";
                break;
            case 'delete':
                return $this->tbl->account->delete($id);
                break;
            case 'check': // read by login
                $r=$this->tbl->account->search(array('login'=>$id));
                return @$r[0];
                break;
            default:
                return $entry;
                break;
        }
        return false;
    }
    function group($act, $id=null, $param=null)
    {
        if (!($entry=$this->dna_verify('group', $id))&&$act!='read') return false;
        $operator = $this->info();
        switch ($act) {
            case 'read':
                return $entry;
                break;
            case 'create':
                if ($operator['dna']!=self::DNA_SYS) {
                    $param['dna'] = $operator['dna'];
                }
                return $this->tbl->group->create($param);
                break;
            case 'modify':
                if (isset($param['dna'])&&$operator['dna']!=self::DNA_SYS) unset($param['dna']); // Only system account can change account's dna 
                return $this->tbl->group->update($id, $param);
                break;
            case 'delete':
                return $this->tbl->group->delete($id);
                break;
        }
        return false;
    }
    function groups($filter=null, $suffix=null)
    {
        $operator = $this->info();
        if (!isset($filter['dna'])&&$operator['dna']!=self::DNA_SYS) $filter['dna'] = $operator['dna'];
        return $this->tbl->group->search($filter, $suffix);
    }
    function disable($userid, $comments=null)
    {
        $session = $this->session_cookie('get');
        $comments .= sprintf("\n done by %s at %s", $session['login'], date('Y-m-d H:i'));
        return $this->tbl->account->update($userid, array('group'=>0, 'comments'=>$comments));
    }
    function domain($act, $id=null, $param=null)
    {
        if (!($entry=$this->dna_verify('domain', $id))&&!in_array($act,array('read','locate'))) return false;
        $operator = $this->info();
        switch ($act) {
            case 'read':
                return $this->tbl->domain->read($id);
                break;
            case 'locate':
                $r = $this->tbl->domain->search($param);
                return @$r[0];
                break;
            case 'create':
                if ($operator['dna']!=self::DNA_SYS) {
                    $param['dna'] = $operator['dna'];
                }
                return $this->tbl->domain->create($param);
                break;
            case 'modify':
                if (isset($param['dna'])&&$operator['dna']!=self::DNA_SYS) unset($param['dna']); // Only system account can change account's dna
                return $this->tbl->domain->update($id, $param);
                break;
            case 'delete':
                return $this->tbl->domain->delete($id);
                break;
        }
        return false;
    }
}
