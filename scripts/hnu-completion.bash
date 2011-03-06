#!bash
#
# Bash completion support for Photon.
#
# This file is part of Photon, the High Speed PHP Framework.
# Copyright (C) 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# http://www.photon-project.com/

have hnu &&
{
_hnu()
{
    local cur prev global_opts commands server_commands

    global_opts="--help --version --verbose --conf="
    commands="init testserver runtests selftest server taskstart secretkey"
    server_commands="start stop less list childstart"

    COMPREPLY=()
    _get_comp_words_by_ref cur prev

    # The hnu command
    if [[ $COMP_CWORD -eq 1 ]]; then
        if [[ "$cur" == --* ]] ; then
            case "$cur" in
                --conf=)
                    COMPREPLY=( $(compgen -f -X "!*.php") )
                    return
                    ;;
                *)
                    COMPREPLY=( $(compgen -W "$global_opts" -- $cur) )
                    ;;
            esac
        else
            COMPREPLY=( $(compgen -W "$commands" -- $cur) )
        fi
        return
    fi

    # prevents completion twice
    case "$cur" in
        --*=)
            COMPREPLY=()
            return
            ;;
    esac

    case "$prev" in
        secretkey)
            case "$cur" in
                --*)
                    COMPREPLY=( $(compgen -W "--length= --help" -- $cur) )
                    ;;
            esac
            ;;
        runtests)
            case "$cur" in
                --bootstrap=)
                    COMPREPLY=( $(compgen -f -X "!*.php") )
                    ;;
                --coverage-html=)
                    COMPREPLY=( $(compgen -d) )
                    ;;
                --*)
                    COMPREPLY=( $(compgen -W "--coverage-html= --bootstrap= --help" -- $cur) )
                    ;;
            esac
            ;;
        selftests)
            case "$cur" in
                --coverage-html=)
                    COMPREPLY=( $(compgen -d) )
                    ;;
                --*)
                    COMPREPLY=( $(compgen -W "--coverage-html= --help" -- $cur) )
                    ;;
            esac
            ;;
        server)
            if [[ "$cur" == --* ]]; then
                COMPREPLY=( $(compgen -W "--all --server-id= --wait= --help" -- $cur) )
            else
                COMPREPLY=( $(compgen -W "$server_commands" -- $cur) )
            fi
            ;;
        list) # a server subcommand
            [[ "$cur" == --* ]] && COMPREPLY=( $(compgen -W "--json --help" $cur) )
            ;;
        start) # a server subcommand
            [[ "$cur" == --* ]] && COMPREPLY=( $(compgen -W "--children= --help" -- $cur) )
            ;;
    esac
}
complete -o bashdefault -o default -o nospace -F _hnu hnu
}