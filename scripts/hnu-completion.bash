#!bash
# hnu-completion
# ==============
# 
# Bash completion support for [Photon](http://www.photon-project.com/).
# 
# Installation
# ------------
# 
# The easiest way (but not necessarily the cleanest) is to copy it somewhere
# (e.g. `~/.hnu-completion.sh`) and put the following line in your `.bashrc`:
# 
#     source ~/.hnu-completion.sh
# 
# Otherwise, the most comprehensive methodology is as follows:
# 
# 1. If you have not already done:
# 
#    1. Create the directory `~/bash_completion.d`.
# 
#    2. Put the following lines in your `.bashrc`:
# 
#       ~~~
#       export USER_BASH_COMPLETION_DIR=~/bash_completion.d
#       if [ -f /etc/bash_completion ]; then
#         . /etc/bash_completion
#       fi
#       ~~~
# 
#       Note: the bash_completion script can be at a different location depending on your system, like:
# 
#         * `/etc/bash_completion` (debian like)
#         * `/usr/local/etc/bash_completion` (BSD like)
#         * `/opt/local/etc/bash_completion` (macports)
# 
#    3. Put in the ~/.bash-completion file the following code:
# 
#       ~~~
#       # source user completion directory definitions
#       if [[ -d $USER_BASH_COMPLETION_DIR && -r $USER_BASH_COMPLETION_DIR && \
#             -x $USER_BASH_COMPLETION_DIR ]]; then
#           for i in $(LC_ALL=C command ls "$USER_BASH_COMPLETION_DIR"); do
#               i=$USER_BASH_COMPLETION_DIR/$i
#               [[ ${i##*/} != @(*~|*.bak|*.swp|\#*\#|*.dpkg*|*.rpm@(orig|new|save)|Makefile*) \
#                  && -f $i && -r $i ]] && . "$i"
#           done
#       fi
#       unset i
#       ~~~
# 
# 2. Copy the hnu-completion file in your ~/bash-completion.d (e.g. `~/bash-completion.d/hnu`).
# 3. Now, open a new shell window and type `hn<TAB> sel<TAB> --c<TAB>/tmp/photon/`. Enjoy!
# 
# LGPL License
# ------------
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

# The following function is based on code from:
# 
# bash completion support for core Git.
#
# Copyright (C) 2006,2007 Shawn O. Pearce <spearce@spearce.org>
# Conceptually based on gitcompletion (http://gitweb.hawaga.org.uk/).
# Distributed under the GNU General Public License, version 2.0.
#
# __hnucomp_1 requires 2 arguments
__hnucomp_1 ()
{
    local c IFS=' '$'\t'$'\n'
    for c in $1; do
        case "$c$2" in
            --*=*) printf %s$'\n' "$c$2"  ;;
            *.)    printf %s$'\n' "$c$2"  ;;
            *)     printf %s$'\n' "$c$2 " ;;
        esac
    done
} # __hnucomp_1 ()

# The following function is based on code from:
# 
# bash completion support for core Git.
#
# Copyright (C) 2006,2007 Shawn O. Pearce <spearce@spearce.org>
# Conceptually based on gitcompletion (http://gitweb.hawaga.org.uk/).
# Distributed under the GNU General Public License, version 2.0.
#
# __hnucomp accepts 1, 2, 3, or 4 arguments
# generates completion reply with compgen
__hnucomp ()
{
    local cur
    _get_comp_words_by_ref cur
    if [ $# -gt 2 ]; then
        cur="$3"
    fi
    case "$cur" in
        --*=)
            COMPREPLY=()
            ;;
        *)
            local IFS=$'\n'
            COMPREPLY=( $(compgen -P "${2-}" \
                        -W "$(__hnucomp_1 "${1-}" "${4-}")" \
                        -- "$cur") )
            ;;
    esac
} # __hnucomp ()

_hnu ()
{
    local cur prev

    COMPREPLY=()
    _get_comp_words_by_ref cur prev

    # The hnu command
    if [[ $COMP_CWORD -eq 1 ]]; then
        if [[ "$cur" == --* ]] ; then
            __hnucomp "--help --version --verbose --conf="
        else
            __hnucomp "init testserver runtests selftest server taskstart secretkey"
        fi
    fi

    case "$prev" in
        secretkey)
            [[ "$cur" == --* ]] && __hnucomp "--length= --help"
            ;;
        runtests)
            [[ "$cur" == --* ]] && __hnucomp "--coverage-html= --bootstrap= --help"
            ;;
        selftest)
            [[ "$cur" == --* ]] && __hnucomp "--coverage-html= --help"
            ;;
        server|--all|--server-id=|--wait=)
            if [[ "$cur" == --* ]]; then
                __hnucomp "--all --server-id= --wait= --help"
            else
                __hnucomp "start stop less list childstart"
            fi
            ;;
        list) # a server subcommand
            [[ "$cur" == --* ]] && __hnucomp "--json --help"
            ;;
        start) # a server subcommand
            [[ "$cur" == --* ]] && __hnucomp "--children= --help"
            ;;
    esac
} # _hnu ()
complete -o bashdefault -o default -o nospace -F _hnu hnu
